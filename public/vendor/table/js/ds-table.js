/**
 * ds-table — client-side search, sort and pagination for any table.
 *
 * Grown out of vendor/table: its comparison logic (data-order override, strict
 * number parse, YMD dates, ka-GE localeCompare) and its ⇅ ↑ ↓ header style are
 * kept as they were. Added here: the search box, page-size select, pager and
 * row-count line, none of which the original had.
 *
 * Markup — nothing but the table itself:
 *   <div class="ds-table" data-ds-table>
 *     <table class="table">…</table>
 *   </div>
 * The toolbar and footer are generated, so every page gets the same ones.
 *
 * Wrapper options (all optional):
 *   data-per-page="10"                    rows per page (defaults to the first option)
 *   data-per-page-options="10,25,50,100"  choices in the select
 *   data-sort="0"                         column index to sort on at load
 *   data-dir="asc"                        direction for it
 *   data-search="false"                   drop the search box
 * Column options on <th>:
 *   data-sortable="false"                 exclude from sorting
 *   data-type="text|number|date"          force the comparison
 * Cell option on <td>:
 *   data-order="2026-07-26"               sort and search on this, not the text
 *
 * Labels come from window.dsTableLabels (see ds_table_script() in helpers.php);
 * the English defaults below are the fallback.
 */
(function () {
  'use strict';

  const DEFAULTS = {
    search: 'Search…',
    perPage: 'Rows per page:',
    showing: 'Showing {from} to {to} of {total}',
    empty: 'Nothing found.',
    prev: 'Previous',
    next: 'Next',
    pages: 'Pages',
  };

  class DsTable {
    constructor(root) {
      this.root = root;
      this.table = root.querySelector('table');
      if (!this.table || !this.table.tBodies[0]) return;

      this.tbody = this.table.tBodies[0];
      this.labels = Object.assign({}, DEFAULTS, window.dsTableLabels || {});

      // Every row is read once. Sorting and searching then work on this array,
      // never on the DOM, which is what keeps a few thousand rows responsive.
      this.rows = Array.from(this.tbody.rows);
      this.rows.forEach((row) => {
        row.dsSearchText = row.cells.length
          ? Array.from(row.cells, (_, i) => cellValue(row, i)).join(' ').toLowerCase()
          : '';
      });

      this.columnCount = this.table.querySelectorAll('thead th').length;
      this.types = {}; // column index → 'text' | 'number' | 'date', worked out on first sort
      this.perPageOptions = (root.dataset.perPageOptions || '10,25,50,100')
        .split(',')
        .map(Number)
        .filter((n) => n > 0);
      this.perPage = Number(root.dataset.perPage) || this.perPageOptions[0] || 10;
      this.query = '';
      this.page = 1;
      this.sortCol = root.dataset.sort === undefined ? null : Number(root.dataset.sort);
      this.dir = root.dataset.dir === 'desc' ? 'desc' : 'asc';

      this.buildToolbar();
      this.buildFooter();
      this.bindHeaders();

      this.filtered = this.rows;
      if (this.sortCol !== null) this.applySort();
      this.render();
    }

    /* ---- chrome ---- */

    buildToolbar() {
      const bar = el('div', 'ds-table-toolbar');

      if (this.root.dataset.search !== 'false') {
        const group = el('div', 'ds-table-search');
        const input = el('input', 'form-control');
        input.type = 'search';
        input.autocomplete = 'off';
        input.placeholder = this.labels.search;
        input.setAttribute('aria-label', this.labels.search);
        group.appendChild(input);

        // Typing filters as you go; 120ms is under the threshold where it feels
        // laggy but still skips most keystrokes on a long list.
        let timer;
        input.addEventListener('input', () => {
          clearTimeout(timer);
          timer = setTimeout(() => {
            this.query = input.value.trim().toLowerCase();
            this.page = 1;
            this.render();
          }, 120);
        });
        bar.appendChild(group);
      }

      const label = el('label', 'ds-table-per-page');
      label.textContent = this.labels.perPage;
      const select = el('select', 'form-select form-select-sm');
      this.perPageOptions.forEach((n) => {
        const opt = new Option(String(n), String(n), false, n === this.perPage);
        select.add(opt);
      });
      select.addEventListener('change', () => {
        this.perPage = Number(select.value);
        this.page = 1;
        this.render();
      });
      label.appendChild(select);
      bar.appendChild(label);

      this.root.insertBefore(bar, this.root.firstChild);
    }

    buildFooter() {
      this.footer = el('div', 'ds-table-footer');
      this.info = el('span', 'ds-table-info text-secondary small');
      this.pager = el('ul', 'pagination pagination-sm mb-0');

      const nav = el('nav');
      nav.setAttribute('aria-label', this.labels.pages);
      nav.appendChild(this.pager);

      this.footer.append(this.info, nav);
      this.root.appendChild(this.footer);

      // One listener for every page button, now and after each re-render.
      this.pager.addEventListener('click', (event) => {
        const link = event.target.closest('[data-page]');
        if (!link) return;
        event.preventDefault();
        this.page = Number(link.dataset.page);
        this.render();
        this.table.scrollIntoView({ block: 'nearest' });
      });
    }

    bindHeaders() {
      this.headers = Array.from(this.table.querySelectorAll('thead th'));
      this.headers.forEach((th, index) => {
        if (th.dataset.sortable === 'false') return;
        th.classList.add('sortable-header');
        th.tabIndex = 0;
        th.setAttribute('role', 'button');
        const sort = () => {
          this.dir = this.sortCol === index && this.dir === 'asc' ? 'desc' : 'asc';
          this.sortCol = index;
          this.page = 1;
          this.applySort();
          this.render();
        };
        th.addEventListener('click', sort);
        th.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            sort();
          }
        });
      });
    }

    /* ---- data ---- */

    /**
     * The column's type is decided once from its own data, not cell by cell: a
     * name column holding one customer called "436044647" is still a name column,
     * and that number must not jump to the top.
     */
    columnType(index) {
      if (this.types[index]) return this.types[index];

      const header = this.headers[index];
      if (header && header.dataset.type) return (this.types[index] = header.dataset.type);

      let seen = 0;
      let numbers = 0;
      let dates = 0;
      for (const row of this.rows) {
        const value = cellValue(row, index);
        if (value === '') continue;
        seen++;
        if (parseStrictNumber(value) !== null) numbers++;
        if (parseYmd(value) !== null) dates++;
      }

      return (this.types[index] =
        seen === 0 ? 'text' : dates === seen ? 'date' : numbers === seen ? 'number' : 'text');
    }

    applySort() {
      const index = this.sortCol;
      const type = this.columnType(index);
      const sign = this.dir === 'asc' ? 1 : -1;

      // Values are read once per sort instead of on every comparison — with ~1500
      // rows that is ~15k reads saved, and it makes the collator the only cost.
      const keys = new Map(this.rows.map((row) => [row, cellValue(row, index)]));

      // Blanks are pulled out rather than compared, so they stay at the bottom in
      // both directions instead of flipping to the top on the second click.
      const filled = [];
      const blank = [];
      this.rows.forEach((row) => (keys.get(row) === '' ? blank : filled).push(row));

      filled.sort((a, b) => sign * compare(keys.get(a), keys.get(b), type));
      this.rows = filled.concat(blank);

      this.headers.forEach((th, i) => {
        th.classList.toggle('sort-asc', i === index && this.dir === 'asc');
        th.classList.toggle('sort-desc', i === index && this.dir === 'desc');
        if (th.classList.contains('sortable-header')) {
          th.setAttribute('aria-sort', i === index ? (this.dir === 'asc' ? 'ascending' : 'descending') : 'none');
        }
      });
    }

    render() {
      this.filtered = this.query
        ? this.rows.filter((row) => row.dsSearchText.includes(this.query))
        : this.rows;

      const total = this.filtered.length;
      const pages = Math.max(1, Math.ceil(total / this.perPage));
      this.page = Math.min(Math.max(1, this.page), pages);

      const start = (this.page - 1) * this.perPage;
      const slice = this.filtered.slice(start, start + this.perPage);

      if (slice.length) {
        this.tbody.replaceChildren(...slice);
      } else {
        const tr = el('tr');
        const td = el('td', 'text-center text-secondary py-4');
        td.colSpan = this.columnCount || 1;
        td.textContent = this.labels.empty;
        tr.appendChild(td);
        this.tbody.replaceChildren(tr);
      }

      this.info.textContent = total
        ? this.labels.showing
            .replace('{from}', start + 1)
            .replace('{to}', start + slice.length)
            .replace('{total}', total)
        : '';

      this.renderPager(pages);
    }

    renderPager(pages) {
      this.pager.replaceChildren();
      if (pages < 2) return;

      const item = (label, page, opts = {}) => {
        const li = el('li', 'page-item' + (opts.disabled ? ' disabled' : '') + (opts.active ? ' active' : ''));
        const a = el('a', 'page-link' + (opts.gap ? ' ds-page-gap' : ''));
        a.href = '#';
        a.textContent = label;
        if (opts.aria) a.setAttribute('aria-label', opts.aria);
        if (!opts.disabled) a.dataset.page = String(page);
        li.appendChild(a);
        this.pager.appendChild(li);
      };

      item('‹', this.page - 1, { disabled: this.page === 1, aria: this.labels.prev });

      // Always five numbers, plus the first and last: 1 2 3 4 5 … 308 at the start,
      // 1 … 4 5 [6] 7 8 … 308 in the middle. Clamping the window rather than the
      // distance keeps the pager the same width wherever you are in the list.
      const start = Math.min(Math.max(1, this.page - 2), Math.max(1, pages - 4));
      const end = Math.min(start + 4, pages);

      let gap = false;
      for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || (i >= start && i <= end)) {
          item(String(i), i, { active: i === this.page });
          gap = false;
        } else if (!gap) {
          item('…', 0, { disabled: true, gap: true });
          gap = true;
        }
      }

      item('›', this.page + 1, { disabled: this.page === pages, aria: this.labels.next });
    }
  }

  /* ---- comparison (kept from vendor/table) ---- */

  function cellValue(row, index) {
    const cell = row.cells[index];
    if (!cell) return '';

    // getAttribute() is checked against null, not falsiness: data-order="" is a
    // deliberate "this cell has no value" (a placeholder badge, an em dash) and
    // must not fall back to the visible text.
    const order = cell.getAttribute('data-order');

    return (order !== null ? order : cell.textContent || '').trim();
  }

  // One collator, reused. Calling localeCompare per comparison instead rebuilds
  // it every time and costs several times as much on a list this size.
  const collator = new Intl.Collator('ka-GE', { numeric: true, sensitivity: 'base' });

  function compare(a, b, type) {
    if (type === 'date') {
      const dateA = parseYmd(a);
      const dateB = parseYmd(b);
      if (dateA !== null && dateB !== null) return dateA - dateB;
    }

    if (type === 'number') {
      const numA = parseStrictNumber(a);
      const numB = parseStrictNumber(b);
      if (numA !== null && numB !== null) return numA - numB;
    }

    return collator.compare(a, b);
  }

  function parseYmd(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (!match) return null;

    const [, y, m, d] = match.map(Number);
    const date = new Date(Date.UTC(y, m - 1, d));
    const valid = date.getUTCFullYear() === y && date.getUTCMonth() === m - 1 && date.getUTCDate() === d;

    return valid ? date.getTime() : null;
  }

  function parseStrictNumber(value) {
    const normalised = value.replace(/\s+/g, '').replace(/,/g, '');
    if (!/^-?\d+(\.\d+)?$/.test(normalised)) return null;

    const parsed = Number(normalised);
    return Number.isFinite(parsed) ? parsed : null;
  }

  function el(tag, className) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    return node;
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ds-table]').forEach((root) => new DsTable(root));
  });

  window.DsTable = DsTable;
})();

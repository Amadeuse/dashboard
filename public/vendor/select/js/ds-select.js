/**
 * ds-select — select2-style searchable dropdown, built as progressive
 * enhancement over a native <select>. Dependency-free.
 *
 * Markup — nothing but the select itself:
 *   <select class="form-select" data-ds-select>
 *     <option value=""></option>                 <!-- empty = placeholder + clear -->
 *     <option value="1">Georgia</option>
 *     <option value="2" disabled>Armenia</option>
 *   </select>
 *   <select class="form-select" multiple data-ds-select>...</select>   <!-- chips -->
 *
 * Options (data-* on the <select>):
 *   data-placeholder="…"          shown when nothing is selected (no-label mode only)
 *   data-search-placeholder="…"   defaults to "Search…"
 *   data-no-results="…"           defaults to "No results."
 *   data-clear-label="…"          aria-label for the clear button, defaults to "Clear"
 *
 * The native <select> is kept in the DOM (moved inside the widget, visually
 * hidden) so the form still submits its value/name exactly as before, and
 * document.getElementById(id) / select.value keep resolving to it exactly as
 * they did before ds-select ever touched the page — its id is never moved.
 *
 * If a <label for="…"> pointing at that id exists anywhere in the document,
 * it is adopted into the widget, repointed at a *derived* id on the trigger
 * (id + "__ds-select-trigger"), and becomes a floating label — same look and
 * motion as vendor/floating-label (same --fl-* tokens, same clear button
 * classes), just driven by :focus (native) and a value-presence class (JS)
 * instead of :placeholder-shown, which a <button> trigger has no equivalent
 * of. Without a label, the trigger falls back to showing data-placeholder.
 *
 * Setting select.value or adding <option>s from other code (a lookup modal,
 * a row-click-to-edit handler, a native form reset) does not by itself
 * refresh what the trigger displays — call select.dsSelect.refresh() right
 * after, or dispatch a 'ds-select:refresh' event on the select.
 *
 * Selecting an option sets select.value (or the matching <option>.selected
 * for multiple) and dispatches a real 'change' event — existing code that
 * listens for it does not need to know ds-select is there.
 *
 * ponytail: dropdown always opens below the trigger, no viewport-edge flip.
 * Add one if a page ever places the select near the bottom of the screen.
 */
(function () {
  'use strict';

  const DEFAULTS = {
    placeholder: 'Select…',
    searchPlaceholder: 'Search…',
    noResults: 'No results.',
    clearLabel: 'Clear',
  };

  class DsSelect {
    constructor(select) {
      this.select = select;
      this.multiple = select.multiple;
      this.placeholder = select.dataset.placeholder || DEFAULTS.placeholder;
      this.searchPlaceholder = select.dataset.searchPlaceholder || DEFAULTS.searchPlaceholder;
      this.noResultsText = select.dataset.noResults || DEFAULTS.noResults;
      this.clearLabel = select.dataset.clearLabel || DEFAULTS.clearLabel;
      this.activeIndex = -1;

      this.onDocClick = this.onDocClick.bind(this);

      this.build();
      this.refresh();
    }

    /* ---- one-time DOM build ---- */

    build() {
      const wrap = el('div', 'ds-select' + (this.select.disabled ? ' disabled' : ''));
      this.select.parentNode.insertBefore(wrap, this.select);
      wrap.appendChild(this.select);
      this.select.classList.add('ds-select-native');
      this.select.tabIndex = -1;
      this.wrap = wrap;

      // The <select> keeps its own id — anything elsewhere on the page that
      // still does document.getElementById(id) (a lookup-modal script, a
      // row-click handler filling the form, ...) must keep getting the real
      // select, not a button with no .value. The trigger gets its own,
      // derived id instead, purely so a <label for> has something to point at.
      const id = this.select.id;
      const triggerId = id ? id + '__ds-select-trigger' : '';

      const trigger = el('button', 'form-select ds-select-trigger');
      trigger.type = 'button';
      if (triggerId) trigger.id = triggerId;
      trigger.setAttribute('role', 'combobox');
      trigger.setAttribute('aria-haspopup', 'listbox');
      trigger.setAttribute('aria-expanded', 'false');
      trigger.disabled = this.select.disabled;
      if (this.select.classList.contains('is-invalid')) trigger.classList.add('is-invalid');
      wrap.appendChild(trigger);
      this.trigger = trigger;

      // Adopt an existing label for this id and repoint it at the trigger.
      this.label = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
      if (this.label) {
        this.label.setAttribute('for', triggerId);
        wrap.appendChild(this.label); // must follow trigger in the DOM: :focus ~ label needs it
        wrap.classList.add('ds-select-floating');
      }

      // Same classes vendor/floating-label uses for its clear button, so the
      // size and the hover-rotate animation are identical, not just similar.
      const clear = el('button', 'btn-close btn-clear');
      clear.type = 'button';
      clear.setAttribute('aria-label', this.clearLabel);
      clear.hidden = true;
      wrap.appendChild(clear);
      this.clearBtn = clear;

      const panel = el('div', 'ds-select-panel');
      panel.hidden = true;
      wrap.appendChild(panel);
      this.panel = panel;

      const search = el('input', 'form-control ds-select-search');
      search.type = 'search';
      search.autocomplete = 'off';
      search.placeholder = this.searchPlaceholder;
      panel.appendChild(search);
      this.search = search;

      const list = el('ul', 'ds-select-list');
      list.setAttribute('role', 'listbox');
      if (this.multiple) list.setAttribute('aria-multiselectable', 'true');
      panel.appendChild(list);
      this.list = list;

      this.bind();
    }

    bind() {
      this.trigger.addEventListener('click', () => (this.panel.hidden ? this.open() : this.close()));
      this.trigger.addEventListener('keydown', (e) => this.onTriggerKey(e));

      this.clearBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        this.setValue('');
      });

      this.search.addEventListener('input', () => this.filter(this.search.value));
      this.search.addEventListener('keydown', (e) => this.onSearchKey(e));

      this.list.addEventListener('click', (e) => {
        const li = e.target.closest('li[data-value]');
        if (li && !li.classList.contains('disabled')) this.pick(li.dataset.value);
      });

      // Options can change after page load (e.g. a value added from a modal
      // elsewhere) — pick that up without the caller having to know about us.
      this.select.addEventListener('ds-select:refresh', () => this.refresh());
    }

    /* ---- read the underlying <select> and redraw ---- */

    refresh() {
      this.options = Array.from(this.select.options).map((o) => ({
        value: o.value,
        label: o.textContent,
        disabled: o.disabled,
      }));
      this.buildListItems();
      this.renderTrigger();
    }

    buildListItems() {
      this.list.replaceChildren();
      this.options
        .filter((o) => !(o.value === '' && o.label === '')) // the placeholder option isn't a pickable row
        .forEach((o) => {
          const li = el('li', 'ds-select-option' + (o.disabled ? ' disabled' : ''));
          li.setAttribute('role', 'option');
          li.dataset.value = o.value;
          li.textContent = o.label;
          this.list.appendChild(li);
        });
    }

    renderTrigger() {
      const selected = this.options.filter((o) => this.select.multiple
        ? Array.from(this.select.selectedOptions).some((s) => s.value === o.value)
        : o.value === this.select.value && o.value !== '');
      const hasValue = selected.length > 0;

      this.trigger.classList.toggle('ds-select-empty', !hasValue);
      // :focus already floats the label live (pure CSS, see ds-select.css);
      // this class is what keeps it floated once the trigger loses focus.
      this.wrap.classList.toggle('ds-select-has-value', hasValue);

      if (!this.multiple) {
        // With an adopted label, the label itself is the resting placeholder —
        // showing our own placeholder text too would double up with it.
        this.trigger.textContent = hasValue ? selected[0].label : (this.label ? '' : this.placeholder);
        this.clearBtn.hidden = !hasValue;
        return;
      }

      this.trigger.replaceChildren();
      if (selected.length === 0) {
        if (!this.label) this.trigger.appendChild(el('span', 'ds-select-placeholder-text', this.placeholder));
      } else {
        selected.forEach((o) => {
          const chip = el('span', 'ds-select-chip');
          chip.append(document.createTextNode(o.label));
          const x = el('span', 'ds-select-chip-remove');
          x.innerHTML = '&times;';
          x.addEventListener('click', (e) => {
            e.stopPropagation();
            this.pick(o.value); // toggling an already-selected value removes it
          });
          chip.appendChild(x);
          this.trigger.appendChild(chip);
        });
      }
      this.clearBtn.hidden = true; // multi-select clears one chip at a time
    }

    /* ---- selecting ---- */

    pick(value) {
      if (this.multiple) {
        const opt = Array.from(this.select.options).find((o) => o.value === value);
        if (opt) opt.selected = !opt.selected;
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
        this.renderTrigger();
        this.markSelected();
        this.search.focus();
        return;
      }

      this.setValue(value);
      this.close();
      this.trigger.focus();
    }

    setValue(value) {
      this.select.value = value;
      this.select.dispatchEvent(new Event('change', { bubbles: true }));
      this.renderTrigger();
    }

    markSelected() {
      const values = new Set(Array.from(this.select.selectedOptions).map((o) => o.value));
      this.list.querySelectorAll('li[data-value]').forEach((li) => {
        li.classList.toggle('active-selected', values.has(li.dataset.value));
        li.setAttribute('aria-selected', values.has(li.dataset.value) ? 'true' : 'false');
      });
    }

    /* ---- filtering ---- */

    filter(query) {
      const q = query.trim().toLowerCase();
      let visible = 0;
      this.list.querySelectorAll('li[data-value]').forEach((li) => {
        const match = li.textContent.toLowerCase().includes(q);
        li.hidden = !match;
        if (match) visible++;
      });

      let empty = this.list.querySelector('.ds-select-no-results');
      if (visible === 0) {
        if (!empty) {
          empty = el('li', 'ds-select-no-results', this.noResultsText);
          this.list.appendChild(empty);
        }
      } else if (empty) {
        empty.remove();
      }

      this.activeIndex = -1;
      this.moveActive(1); // land on the first visible, enabled row
    }

    /* ---- open / close ---- */

    open() {
      if (this.trigger.disabled) return;
      this.panel.hidden = false;
      this.trigger.setAttribute('aria-expanded', 'true');
      this.search.value = '';
      this.filter('');
      this.markSelected();
      this.search.focus();
      document.addEventListener('click', this.onDocClick);
    }

    close() {
      this.panel.hidden = true;
      this.trigger.setAttribute('aria-expanded', 'false');
      document.removeEventListener('click', this.onDocClick);
    }

    onDocClick(e) {
      if (!this.wrap.contains(e.target)) this.close();
    }

    /* ---- keyboard ---- */

    onTriggerKey(e) {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) {
        e.preventDefault();
        this.open();
      }
    }

    onSearchKey(e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); this.moveActive(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); this.moveActive(-1); }
      else if (e.key === 'Enter') {
        e.preventDefault();
        const li = this.rows()[this.activeIndex];
        if (li) this.pick(li.dataset.value);
      } else if (e.key === 'Escape') {
        this.close();
        this.trigger.focus();
      } else if (e.key === 'Tab') {
        this.close();
      }
    }

    rows() {
      return Array.from(this.list.querySelectorAll('li[data-value]:not([hidden])'));
    }

    moveActive(dir) {
      const rows = this.rows().filter((li) => !li.classList.contains('disabled'));
      if (!rows.length) return;

      rows.forEach((li) => li.classList.remove('active'));

      // -1 means "nothing active yet": step from just outside either end, so
      // dir=+1 lands on the first row and dir=-1 lands on the last.
      const next = Math.max(0, Math.min(rows.length - 1, this.activeIndex + dir));

      this.activeIndex = next;
      rows[next].classList.add('active');
      rows[next].scrollIntoView({ block: 'nearest' });
    }
  }

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select[data-ds-select]').forEach((select) => {
      select.dsSelect = new DsSelect(select);
    });
  });

  window.DsSelect = DsSelect;
})();

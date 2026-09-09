/**
 * ds-date-range — wires flatpickr's range mode onto a ds-date-range field.
 *
 * The visible input is display-only (no name, never submitted); the two
 * hidden from/to inputs beside it are what the surrounding form actually
 * posts, always as plain 'Y-m-d', whatever the calendar shows the user.
 * That split is the whole point: the server contract (?from=&to=) never had
 * to change when this stopped being a pair of native date inputs (4.106 in
 * handoff.md).
 *
 * Markup + the CSS half: see ds-date-range.css. Options come off the root:
 *   data-max="2026-09-05"   latest selectable day (no future invoices exist)
 * Locale follows <html lang> when flatpickr ships that translation, English
 * otherwise — a missing locale file degrades to English, never to a crash.
 */
(function () {
  'use strict';

  function wire(root) {
    const display = root.querySelector('[data-ds-date-range-display]');
    const fromInput = root.querySelector('[data-ds-date-range-from]');
    const toInput = root.querySelector('[data-ds-date-range-to]');
    if (!display || !fromInput || !toInput) return;

    const lang = document.documentElement.lang;
    const locale = flatpickr.l10ns && flatpickr.l10ns[lang] ? lang : 'default';

    const initial = [fromInput.value, toInput.value]
      .filter(Boolean)
      .map((value) => flatpickr.parseDate(value, 'Y-m-d'))
      .filter(Boolean);

    flatpickr(display, {
      mode: 'range',
      dateFormat: 'Y-m-d',
      showMonths: 2,
      maxDate: root.dataset.max || null,
      defaultDate: initial.length ? initial : null,
      locale: locale,
      onChange: function (dates, _text, instance) {
        const iso = (date) => instance.formatDate(date, 'Y-m-d');

        // One click into a range is a valid single-day filter on its own —
        // better than posting a half-filled range the server then ignores.
        fromInput.value = dates[0] ? iso(dates[0]) : '';
        toInput.value = dates[1] ? iso(dates[1]) : (dates[0] ? iso(dates[0]) : '');
      },
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (typeof flatpickr !== 'function') return; // library missing: field stays a plain read-only box
    document.querySelectorAll('[data-ds-date-range]').forEach(wire);
  });
})();

/**
 * floating-label — clear button behaviour for .form-floating inputs.
 *
 * From public/vendor/fl.loc/script.js. The two listeners that showed and hid the
 * button are gone: :not(:placeholder-shown) ~ .btn-clear does that in CSS, with
 * no work per keystroke and no inline styles left behind.
 *
 * One delegated listener, so inputs added later work without re-initialising.
 */
document.addEventListener('click', (event) => {
  const button = event.target.closest('.btn-clear');
  if (!button) return;

  const input = button.closest('.form-floating')?.querySelector('.form-control');
  if (!input) return;

  input.value = '';
  input.focus();

  // Both events, so anything listening (validation, a ds-table search) reacts
  // exactly as it would to the user emptying the field by hand.
  input.dispatchEvent(new Event('input', { bubbles: true }));
  input.dispatchEvent(new Event('change', { bubbles: true }));
});

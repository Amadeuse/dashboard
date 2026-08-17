/**
 * floating-label — clear button and password show/hide for .form-floating inputs.
 *
 * The clear button is from public/vendor/fl.loc/script.js. The two listeners
 * that showed and hid the button are gone: :not(:placeholder-shown) ~ .btn-clear
 * does that in CSS, with no work per keystroke and no inline styles left behind.
 *
 * One delegated listener for both buttons, so inputs added later work without
 * re-initialising.
 */
document.addEventListener('click', (event) => {
  const clear = event.target.closest('.btn-clear');
  if (clear) {
    const input = clear.closest('.form-floating')?.querySelector('.form-control');
    if (!input) return;

    input.value = '';
    input.focus();

    // Both events, so anything listening (validation, a ds-table search) reacts
    // exactly as it would to the user emptying the field by hand.
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    return;
  }

  // Password show/hide — .form-floating--password's marker class (not the
  // input's own [type]) is what CSS keys the extra padding off, so flipping
  // the type here doesn't shift the layout.
  const toggle = event.target.closest('.btn-toggle-password');
  if (toggle) {
    const input = toggle.closest('.form-floating')?.querySelector('.form-control');
    if (!input) return;

    const nowVisible = input.type === 'password';
    input.type = nowVisible ? 'text' : 'password';
    toggle.querySelector('i')?.classList.toggle('bi-eye', !nowVisible);
    toggle.querySelector('i')?.classList.toggle('bi-eye-slash', nowVisible);
    toggle.setAttribute('aria-label', nowVisible ? toggle.dataset.hideLabel : toggle.dataset.showLabel);
    input.focus();
  }
});

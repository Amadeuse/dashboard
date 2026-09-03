(() => {
  const root = document.documentElement;
  const stored = localStorage.getItem("ds-theme");
  if (stored) root.setAttribute("data-bs-theme", stored);

  document.querySelectorAll("[data-theme-toggle]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const next = root.getAttribute("data-bs-theme") === "dark" ? "light" : "dark";
      root.setAttribute("data-bs-theme", next);
      localStorage.setItem("ds-theme", next);
    });
  });

  document.querySelectorAll("[data-sidebar-toggle]").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.body.classList.toggle("ds-sidebar-collapsed");
    });
  });

  document.querySelectorAll("[data-module-toggle]").forEach((form) => {
    form.querySelector("input[type=checkbox]").addEventListener("change", () => form.submit());
  });

  // Server-flashed validation errors (.is-invalid + .invalid-feedback) never
  // cleared once the user fixes the field — fix live instead of making them
  // reload. Two DOM shapes in play: most fields' .invalid-feedback sits as a
  // sibling of .form-floating (or .input-group, when the field has one); a
  // dynamic invoice item row (see Invoice::validate()'s items_N errors)
  // shares ONE is-invalid/.invalid-feedback across all its fields instead.
  const clearInvalid = (event) => {
    const field = event.target;
    if (!field.matches("input, select, textarea")) return;

    const row = field.closest("[data-item-row]");
    if (row) {
      row.querySelectorAll(".is-invalid").forEach((el) => el.classList.remove("is-invalid"));
      row.querySelector(".invalid-feedback")?.remove();
      // A fully-empty row carries no per-field error at all — "add at least
      // one product" (Invoice::validate()) instead renders as a plain
      // .alert-danger just above #invoiceItems. Any row edit means the user
      // is addressing that too.
      field.closest("#invoiceItems")?.parentElement.querySelector(".alert-danger")?.remove();
      return;
    }

    field.classList.remove("is-invalid");
    field.closest(".ds-select")?.querySelector(".ds-select-trigger")?.classList.remove("is-invalid");

    const wrapper = field.closest(".input-group") || field.closest(".form-floating");
    if (wrapper?.nextElementSibling?.classList.contains("invalid-feedback")) {
      wrapper.nextElementSibling.remove();
    }
  };
  document.addEventListener("input", clearInvalid);
  document.addEventListener("change", clearInvalid);

  // Global toast helper — originally just for errors caught client-side
  // (e.g. a file rejected before it's even submitted, no page reload to
  // hang a flash message off of), now every flash message (e.g. "customer
  // added") goes through it too (ds_flash_toast(), helpers.php — 4.82 in
  // handoff.md), not a page-top .alert banner. Standard Bootstrap toast
  // layout (4.83) — plain white toast, .toast-header (icon, app name,
  // "just now", close) + .toast-body — not the colored text-bg-{type}
  // block this used to be; only the header icon still carries the type's
  // color, .btn-close is always the right (dark) one now that the toast
  // itself is never a dark background. `message` is always our own
  // translated text, never raw user input, so it's fine to drop straight
  // into innerHTML.
  const DS_TOAST_ICON = {
    success: "bi-check-circle-fill",
    danger: "bi-exclamation-octagon-fill",
    warning: "bi-exclamation-triangle-fill",
    info: "bi-info-circle-fill",
  };
  const DS_TOAST_COLOR = {
    success: "text-success",
    danger: "text-danger",
    warning: "text-warning",
    info: "text-info",
  };
  window.dsNotify = (message, type = "danger", icon = null) => {
    const container = document.getElementById("dsToastContainer");
    if (!container) return;

    const iconClass = icon || DS_TOAST_ICON[type] || "bi-bell-fill";
    const colorClass = DS_TOAST_COLOR[type] || "text-secondary";

    const toastEl = document.createElement("div");
    toastEl.className = "toast";
    toastEl.setAttribute("role", "alert");
    toastEl.innerHTML = `
      <div class="toast-header">
        <i class="bi ${iconClass} ${colorClass} me-2"></i>
        <strong class="me-auto">${window.dsAppName ?? ""}</strong>
        <small class="text-body-secondary">${window.dsToastJustNow ?? ""}</small>
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body">${message}</div>`;
    container.appendChild(toastEl);

    const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { autohide: true, delay: 6000 });
    toastEl.addEventListener("hidden.bs.toast", () => toastEl.remove());
    toast.show();
  };

  // The catalog-driven service: app/config/notifications.php lists every
  // known code with its type + lang key, and window.dsNotifications (see
  // layout.php) already has the text resolved for the current locale. A
  // page just calls dsNotifyCode(4418) — no per-field data-* plumbing.
  const DS_NOTIFY_BOOTSTRAP_TYPE = { error: "danger", warning: "warning", success: "success" };
  window.dsNotifyCode = (code) => {
    const entry = window.dsNotifications?.[code];
    if (!entry) {
      window.dsNotify(`#${code}`, "danger"); // unregistered code — loud, not silent
      return;
    }
    window.dsNotify(entry.text, DS_NOTIFY_BOOTSTRAP_TYPE[entry.type] ?? "danger");
  };
})();

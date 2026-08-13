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

  // Flash messages (e.g. "customer added") fade out on their own — no close
  // button needed. bootstrap.Alert already owns the fade + DOM removal, so
  // this just schedules the same close() a manual dismiss button would call.
  document.querySelectorAll(".ds-alert-autodismiss").forEach((el) => {
    setTimeout(() => bootstrap.Alert.getOrCreateInstance(el).close(), 4000);
  });

  // Global toast helper — for errors caught client-side (e.g. a file rejected
  // before it's even submitted), where there's no page reload to hang a flash
  // message off of. `message` is always our own translated text, never raw
  // user input, so it's fine to drop straight into innerHTML.
  window.dsNotify = (message, type = "danger") => {
    const container = document.getElementById("dsToastContainer");
    if (!container) return;

    const toastEl = document.createElement("div");
    toastEl.className = `toast align-items-center text-bg-${type} border-0`;
    toastEl.setAttribute("role", "alert");
    toastEl.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>`;
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

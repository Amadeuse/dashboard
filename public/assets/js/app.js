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
})();

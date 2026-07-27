# Nova Dashboard

PHP 8.2 dashboard on a small hand-rolled MVC. Bootstrap 5.3 design system, Georgian/English, no database.

## 👉 Read `handoff.md` first

`handoff.md` holds the current state, the non-obvious decisions behind the code, what is
verified vs unverified, and the next steps. **Read it before changing anything** — several
constraints there (Bootstrap overrides, Georgian font behaviour, browser-testing pitfalls)
are impossible to infer from the code and have already cost one wrong fix each.

## Quick facts

- Run: `cd public && C:/OSPanel/modules/PHP-8.2/php.exe -S 127.0.0.1:8090 index.php`
  (the `index.php` argument is required — it acts as the router script)
- Docroot is `public/`; `app/` sits outside it on purpose.
- Routes: `app/routes.php`. Data: `app/Models/Dashboard.php`. Strings: `app/lang/{ka,en}.php`.
- **Reply to the user in Georgian.**

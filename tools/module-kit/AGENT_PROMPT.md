# Module author briefing — Nova Dashboard

Paste this whole file to an AI agent (Claude, Copilot, Cursor, …) as the first
message when asking it to build a module for this system. It is the complete
contract; nothing outside it is guaranteed to exist.

Fill in the two blanks at the bottom (`MODULE CODE`, `WHAT IT SHOULD DO`) and
send.

---

## 1. What you are building

A **module** for Nova Dashboard — a PHP 8.2 invoicing application on a small
hand-rolled MVC (no framework, no Composer packages except mpdf for PDFs).

**The one rule, above everything else:**

> **Installing a module changes no core file.**

If your design needs a core file edited, the design is wrong. Either there is
an existing extension point you have not used, or a new one has to be added to
core first — say so and stop, rather than patching core.

This rule is not stylistic. The previous module system was deleted whole
because its two modules put their translations in the core language files and
their markup inside core views: removing a module left its traces behind in
core, which made third-party modules impossible.

## 2. Environment

- PHP 8.2, `declare(strict_types=1)` in every file
- MySQL 8.4, PDO with `ATTR_EMULATE_PREPARES => false` — **always** use bound
  parameters, never string interpolation in SQL
- Bootstrap 5.3 + Bootstrap Icons, already loaded — do not add a CSS framework
- The app is bilingual: **Georgian (`ka`) and English (`en`)**, both required
- No Composer, no npm, no build step. Files you write are the files that run.
- Autoloading: `App\Modules\YourCode\Models\Thing` → `app/Modules/YourCode/Models/Thing.php`

## 3. Directory layout

```
app/Modules/YourCode/
├── module.json          REQUIRED  manifest
├── Module.php           REQUIRED  entry point
├── lang/ka.php          your own translations (ka + en)
│   └── en.php
├── assets/module.css    your own CSS/JS, loaded automatically
│   └── module.js
├── Controllers/         your own controllers
├── Models/              your own data access
├── Views/               your own pages
├── menu.json            sidebar entry (optional)
├── migrations/*.sql     your own tables
├── uninstall.sql        DROP TABLE for every table you created
└── tests/*_test.php     your tests
```

`YourCode` is the folder name, the PHP namespace segment, and the module code.
**Letters and digits only, starting with a letter** — `Warehouse`, `TimeLog2`.
No dashes, no underscores. The single folder inside your ZIP must match it.

## 4. module.json

```json
{
  "name":        "yc.module_name",
  "description": "yc.module_description",
  "version":     "1.0.0",
  "author":      "Your Name",
  "icon":        "bi-box"
}
```

`name`/`description` may be literal text or your own translation keys —
`t()` returns the key unchanged when there is no translation, so both work.
`icon` must be a Bootstrap Icons class (`bi-…`) or it is replaced with a
default.

## 5. Module.php

```php
<?php
declare(strict_types=1);

namespace App\Modules\YourCode;

use App\Core\{Hooks, Lang, ModuleInterface, ModuleRouter};

final class Module implements ModuleInterface
{
    public function register(ModuleRouter $router): void
    {
        $router->get('/list',  [Controllers\ThingController::class, 'index']);
        $router->post('/save', [Controllers\ThingController::class, 'save']);

        Lang::loadModule('YourCode');

        Hooks::on('render.invoice.row.badges', static fn(array $ctx): string => '');
    }
}
```

`register()` runs **once per request, on every request**, for tenants who have
the module enabled. Register routes and hooks and nothing else — no queries,
no output, no side effects.

It runs inside `try/catch`. If it throws, your module is skipped and the rest
of the app keeps working (and the settings page shows the error).

## 6. Routing — the `/m/` prefix

`register()` receives a **`ModuleRouter`**, not the real router. Every path you
register is forced under `/m/<lowercased code>/`:

| You register | Real URL |
|---|---|
| `/list` | `/m/yourcode/list` |
| `/save` | `/m/yourcode/save` |

You **cannot** register outside that prefix. When you write a `<form action>`
or a link in your own views and hooks, write the full prefixed path.

Only `get()`, `post()` and `add($method, …)` exist, and handlers are
`[ControllerClass::class, 'method']` arrays — **not** closures. Routes are
matched as literal paths: there are no path parameters, so pass ids in the
query string (`?id=5`) or in POST fields.

## 7. Hook points

These four exist. There are no others — do not invent hook names.

| Point | Kind | Context | Return |
|---|---|---|---|
| `invoice.list.data` | data | `['ids' => list<int>]` | `['YourCode' => [id => mixed]]` |
| `render.invoice.row.badges` | render | `['invoice' => array, 'data' => array]` | HTML string |
| `render.invoice.form.aside` | render | `['invoice' => ?array]` | HTML string |
| `invoice.saved` | event | `['id' => int, 'isNew' => bool]` | ignored |

**data** hooks exist so a listing can be enriched with one query for the whole
page. Never query per row — core hands you every id at once. Key your answer
by your module code so results from several modules can be merged.

**render** hooks must return a string (`''` for nothing) and must escape
everything with `e()`.

`render.invoice.form.aside` receives `invoice => null` while a new invoice is
still unsaved — return `''` then, there is nothing to attach to yet.

If you need an extension point that is not on this list, **say so and stop**.
Adding one is a core change, and a core change is not yours to make.

## 8. Core API you may use

```php
// Database
Db::conn(): PDO                         // prepare()/execute(), always bound params
Db::all(string $sql, array $params=[])  // SELECT helper, returns array of rows

// Who is acting
Auth::requireUser(): array              // the signed-in user, or redirect to /login
Auth::tenantId(): int                   // the tenant (`ruler`) that owns the data
Auth::requireAdmin(): array             // admin-only actions
Auth::invoiceScopeUserIds(): array      // which users' invoices are visible now

// Translation
t('yc.key')                             // your own key, after Lang::loadModule()
t('yc.key', $a, $b)                     // vsprintf args

// Views (extend App\Core\Controller)
$this->viewAt(__DIR__ . '/../Views/x.php', $data)   // YOUR view, in the app layout
// NOT $this->view() — that looks under app/Views/, which is core's

// Helpers available everywhere
e(string): string                       // HTML escape — use on every output
csrf_field(): string                    // hidden token input, in every POST form
csrf_verify(): void                     // at the top of every POST handler
redirect(string): never
flash(string $key, mixed $value = null) // one-request message
ds_flash_toast(?string $text)           // toast markup for $scripts
money(float, string $currency): string
ds_date(string $iso): string
```

Anything not listed here may change without notice. Do not reach into core
models (`App\Models\*`) — read core data through SQL against its tables, or ask
for a hook.

## 9. Multi-tenancy — read this twice

Every row your module stores belongs to a tenant. Your table needs a `ruler`
column, and **every** query must filter on it:

```php
// correct
Db::all('SELECT * FROM my_table WHERE ruler = ?', [Auth::tenantId()]);

// correct — ownership in the WHERE clause, not just the id
$pdo->prepare('UPDATE my_table SET label = ? WHERE id = ? AND ruler = ?')
    ->execute([$label, $id, Auth::tenantId()]);

// WRONG — any tenant can read or overwrite any row by guessing an id
Db::all('SELECT * FROM my_table WHERE id = ?', [$id]);
```

Take the tenant from `Auth::tenantId()`, **never** from a form field or query
string. This is the most common and most damaging module bug, and it is
invisible when testing by hand because you are usually signed in as one tenant.
The template's tests cover it — keep those tests.

If your row belongs to a core row, declare the foreign key with
`ON DELETE CASCADE` so deleting the core row cleans up after you.

## 10. Security — your responsibility, not the platform's

Module code runs with the same privileges core has. The `/m/` prefix prevents
URL collisions, **not** access. There is no sandbox.

Non-negotiable in every module:

- `csrf_verify()` at the top of **every** POST handler
- an ownership check on **every** id that arrives from the browser
- `e()` on **every** value rendered into HTML
- bound parameters in **every** query — never build SQL by concatenation
- never `echo` raw user input, never `eval`, never `unserialize` user data

## 11. Visual rules

Your module must look like the app, not like a guest.

**Do**
- use core classes: `.ds-card`, `.ds-table`, `.form-floating`, Bootstrap utilities
- take colour from tokens: `var(--bs-primary)`, `var(--ds-radius)`,
  `var(--bs-border-color)`, and semantic utilities like `bg-success-subtle`
- keep `assets/module.css` to layout only — grid, gap, widths

**Do not**
- write hard-coded colours (`#0d6efd`, `rgb(...)`) — they break dark mode
- ship your own font or size scale
- override core classes (`.btn`, `.card`) themselves
- add a CSS framework or a JS library

Every `<select>` in this app uses the searchable component: add
`data-ds-select` to it. Every `.form-floating` text input gets a clear button
automatically.

## 12. Migrations and uninstall

`migrations/001_create_*.sql` runs once, on install, tracked as
`YourCode/001_…` in a shared ledger. Name files with a numeric prefix; they run
in filename order. Never edit a migration that has shipped — add a new one.

`uninstall.sql` must `DROP TABLE` every table you created. The settings page
reads those `DROP TABLE` lines to know which tables to export to
`storage/backups/` before removing your module — a table missing from this file
is a table whose data is silently lost on uninstall.

## 13. Testing — required

```bash
php tools/module-kit/module-test.php YourCode
```

The harness copies the live schema (structure only, never a row) into a scratch
database, runs your migrations, seeds three users, signs one in, boots your
module, and runs `tests/*_test.php`. The database is dropped afterwards. Your
tests cannot touch real data.

Fixtures available in every test:

| id | who |
|---|---|
| 1 | tenant A — signed in by default |
| 2 | tenant B — a **separate** owner, for isolation tests |
| 3 | a sub-user of tenant A |

Four assertions, no framework:

```php
test('name', function () { ... });
assert_true($value, 'message');
assert_same($expected, $actual, 'message');
assert_throws(fn() => ..., 'message');
```

Switch tenants inside a test to prove isolation:

```php
test('another tenant cannot read this row', function () {
    $id = Thing::save(['label' => 'secret']);

    $_SESSION['user_id'] = 2;
    try {
        assert_same(null, Thing::find($id));
    } finally {
        $_SESSION['user_id'] = 1;
    }
});
```

Every module must ship tenant-isolation tests for read, update and delete.

## 14. Packaging

```
YourCode.zip
└── YourCode/
    ├── module.json
    └── Module.php  …
```

Exactly one top-level folder, named for the module code. Max 20 MB, 2000 files.
Allowed extensions: `php json sql css js md txt svg png jpg jpeg gif webp woff
woff2`. A path containing `..`, an absolute path, or a backslash rejects the
whole archive.

Install through **Settings → Modules → Upload**, then *Install*, then *Enable*.

## 15. Start from the template

`tools/module-kit/ModuleTemplate/` is a complete, working, tested module.
Copy it, rename the folder, the namespace and the `tpl.` key prefix, and
replace the example logic. It already demonstrates every part of this contract
and its tests pass.

---

## YOUR TASK

**MODULE CODE:** `______`  (letters and digits, starting with a letter)

**WHAT IT SHOULD DO:**

```
______
```

Build it by copying `tools/module-kit/ModuleTemplate/`. When you are done:

1. `php -l` every PHP file you wrote
2. `php tools/module-kit/module-test.php <YourCode>` — all tests must pass,
   including tenant isolation for read, update and delete
3. Confirm `grep -rn "app/lang\|app/Views\|app/Controllers" app/Modules/<YourCode>`
   returns nothing — you must not have touched core
4. List every hook point you used and why

If the task needs something this contract does not offer, stop and say what is
missing instead of working around it.

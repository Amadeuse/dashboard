# Module kit

Everything needed to build, test and ship a module for Nova Dashboard.

```
tools/module-kit/
├── README.md          this file
├── AGENT_PROMPT.md    paste to an AI agent before asking it to build a module
├── ModuleTemplate/    a complete, working, tested module to copy
└── module-test.php    the test harness
```

## Build a module

```bash
cp -r tools/module-kit/ModuleTemplate app/Modules/YourCode
```

Then rename, in this order:

1. the folder — `YourCode` (letters and digits, starting with a letter)
2. `namespace App\Modules\ModuleTemplate` → `App\Modules\YourCode` in every PHP file
3. the `tpl.` key prefix in `lang/*.php` and everywhere `t('tpl.…')` appears
4. `/m/moduletemplate/` → `/m/yourcode/` in the views and hooks
5. the table name in `migrations/001_*.sql` and `uninstall.sql`

## Test it

```bash
php tools/module-kit/module-test.php YourCode
php tools/module-kit/module-test.php YourCode --keep   # leave the scratch DB for inspection
```

The harness copies the **live schema** — structure only, never a row — into a
database called `<your db>_moduletest`, runs your migrations there, seeds
users, boots your module against the real core, runs `tests/*_test.php`, and
drops the database. Your tests cannot see or change real data.

It boots real core rather than stubs on purpose: a stubbed `Db`/`Auth`/`Hooks`
would be a second implementation that drifts, and a module would pass its tests
while breaking in the app.

### Fixtures

| id | who |
|---|---|
| 1 | tenant A — signed in by default |
| 2 | tenant B — a separate owner, for isolation tests |
| 3 | a sub-user of tenant A |

### Assertions

```php
test('name', function () { ... });
assert_true($value, 'message');
assert_same($expected, $actual, 'message');
assert_throws(fn() => ..., 'message');
```

### Prove your isolation tests actually work

A tenant-isolation test that passes for the wrong reason is worse than none.
Check it catches the bug it is supposed to: remove `AND ruler = ?` from your
model's `find()`, run the tests, and confirm they go red. Put it back.

## Build a module with an AI agent

Send `AGENT_PROMPT.md` as the first message, with the two blanks at the bottom
filled in. It carries the whole contract: layout, routing, the four hook
points, the core API, the multi-tenancy rule, the security requirements, the
visual rules and the test commands.

## Where to develop: the modules.loc sandbox

Developing a module inside the live project has one real risk, and it is not
the code — it is **install**. `ModuleRegistry::install()` runs a module's
migrations against whatever database the app is pointed at, so a wrong
`CREATE TABLE` or `ALTER` in a module you are still writing lands on real data.
The test harness is isolated; clicking *Install* in the settings page is not.

So module work happens in a **second instance of this same repository**:

| | dashboard.loc | modules.loc |
|---|---|---|
| code | this repo | a clone of it |
| database | the real one | its own (`invoice_dev`) |
| outbound mail / API keys | configured | blank on purpose |

One codebase, two environments. Not a second copy of the core — a stripped
"module SDK" would drift from core the moment core changed, and a module would
pass against the SDK while breaking in the app. Same reason the test harness
boots real core instead of stubs.

### Setting one up

```
git clone <this repo> C:/OSPanel/home/modules.loc
cp -r vendor C:/OSPanel/home/modules.loc/vendor      # or: composer install
```

`.osp/project.ini` (OSPanel reads the folder name as the domain):

```ini
[modules.loc]

http_engine = Apache
php_engine  = PHP-8.2
web_root    = {base_dir}/public
```

Then copy `.env.example` to `.env` and change what must differ — **`DB_NAME`
above all**, since a shared database defeats the whole point — and blank
`MAIL_*`, `PIXABAY_API_KEY`, `GOOGLE_CLIENT_*` and `SMS_*` so a sandbox cannot
send mail or spend quota. Create the database, then:

```
php migrate.php
```

Restart OSPanel so the new vhost is picked up.

### Working in it

```
cd C:/OSPanel/home/modules.loc
git pull origin main                    # take core changes
cp -r tools/module-kit/ModuleTemplate app/Modules/YourCode
php tools/module-kit/module-test.php YourCode
```

Install and enable it from **Settings → Modules**, click around, break things.
Nothing here can reach real data. When the module is finished, copy the folder
back (or commit it from this instance) and ship the ZIP.

## Ship it

```
YourCode.zip
└── YourCode/
    ├── module.json
    └── Module.php  …
```

Exactly one top-level folder, named for the module code. Install through
**Settings → Modules → Upload**, then *Install*, then *Enable*.

The full author's guide lives in the app itself at **/help/modules**.

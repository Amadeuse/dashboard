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

<?php

declare(strict_types=1);

/**
 * Module test harness (4.114) — runs a module's own tests against a real core,
 * in a database of its own.
 *
 *     php tools/module-kit/module-test.php YourCode
 *     php tools/module-kit/module-test.php YourCode --keep   (don't drop the DB)
 *
 * Why it boots the real core instead of stubbing it: a hand-written stub of
 * Db/Auth/Hooks/Router would be a second implementation of the thing being
 * tested, and it would drift the first time core changed — the module would
 * pass here and break in the app. So this loads app/bootstrap.php exactly as
 * public/index.php does.
 *
 * What makes it safe to run against real code is the database: DB_NAME is
 * redirected to "<your db>_moduletest" before a single connection is opened,
 * the live schema is copied there (structure only, never a row), and the whole
 * database is dropped at the end. A module's tests therefore cannot see,
 * change or delete anything in the live database — which matters, because
 * module tests are third-party code too.
 *
 * A test file is plain PHP with assertions, no framework:
 *
 *     // YourCode/tests/example_test.php
 *     test('stores a row', function () {
 *         Example::save(['name' => 'x']);
 *         assert_same(1, count(Example::all()));
 *     });
 */

$root = dirname(__DIR__, 2);

// ---------------------------------------------------------------------------
// Arguments
// ---------------------------------------------------------------------------
$code = $argv[1] ?? '';
$keep = in_array('--keep', $argv, true);

if ($code === '') {
    fwrite(STDERR, "usage: php tools/module-kit/module-test.php <ModuleCode> [--keep]\n");
    exit(2);
}

require $root . '/app/bootstrap.php';

use App\Core\Db;
use App\Core\Env;
use App\Core\Migrator;
use App\Core\ModuleRegistry;
use App\Core\Router;

if (!ModuleRegistry::isValidCode($code)) {
    fwrite(STDERR, "Invalid module code: $code (letters and digits only, starting with a letter)\n");
    exit(2);
}

$moduleDir = ModuleRegistry::dir($code);
if (!is_dir($moduleDir)) {
    fwrite(STDERR, "No such module: $moduleDir\n");
    exit(2);
}

// ---------------------------------------------------------------------------
// Test assertions — deliberately four functions, not a framework
// ---------------------------------------------------------------------------
$GLOBALS['ds_tests'] = ['pass' => 0, 'fail' => 0, 'failures' => []];

function test(string $name, callable $body): void
{
    try {
        $body();
        $GLOBALS['ds_tests']['pass']++;
        echo "  \u{2713} $name\n";
    } catch (\Throwable $e) {
        $GLOBALS['ds_tests']['fail']++;
        $GLOBALS['ds_tests']['failures'][] = $name . ' — ' . $e->getMessage();
        echo "  \u{2717} $name\n      " . $e->getMessage() . "\n";
    }
}

function assert_true(mixed $value, string $message = 'expected true'): void
{
    if ($value !== true) {
        throw new \RuntimeException($message . ' (got ' . var_export($value, true) . ')');
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = 'values differ'): void
{
    if ($expected !== $actual) {
        throw new \RuntimeException(
            $message . ' — expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

function assert_throws(callable $body, string $message = 'expected an exception'): void
{
    try {
        $body();
    } catch (\Throwable) {
        return;
    }

    throw new \RuntimeException($message);
}

// ---------------------------------------------------------------------------
// Scratch database — set BEFORE anything opens a connection
// ---------------------------------------------------------------------------
$liveDb = (string) env('DB_NAME', 'dashboard');
$testDb = $liveDb . '_moduletest';
Env::set('DB_NAME', $testDb);

// Connect to the server without selecting a database, to create it.
$dsn    = sprintf('mysql:host=%s;charset=utf8mb4', (string) env('DB_HOST', 'localhost'));
$server = new PDO($dsn, (string) env('DB_USER', 'root'), (string) env('DB_PASS', ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// A leftover database from an interrupted --keep run would hide a broken
// migration, so always start from nothing.
$server->exec("DROP DATABASE IF EXISTS `$testDb`");
$server->exec("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

echo "module-test: $code\n";
echo "  database: $testDb (scratch — the live '$liveDb' is never touched)\n";

$exitCode = 1;

try {
    // -----------------------------------------------------------------------
    // Schema: the live database's structure, copied table by table — no rows.
    //
    // Replaying migrations/ would also work now (4.115 repaired that — it was
    // broken when this harness was written, which is how the bug was found),
    // but copying the live structure is still the better source: a module gets
    // tested against exactly the schema it will run against in production,
    // rather than against whatever a replay of history produces. It is also
    // far faster than 38 migrations per run.
    //
    // Only SHOW CREATE TABLE is read from the live database — never a row.
    // -----------------------------------------------------------------------
    $live = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', (string) env('DB_HOST', 'localhost'), $liveDb),
        (string) env('DB_USER', 'root'),
        (string) env('DB_PASS', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM]
    );

    $pdo = Db::conn();
    // Tables reference each other, and SHOW TABLES gives no dependency order,
    // so constraints are switched off for the copy rather than sorted.
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $copied = 0;
    foreach ($live->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $create = $live->query("SHOW CREATE TABLE `$table`")->fetch()[1];
        $pdo->exec($create);
        $copied++;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "  schema copied: $copied tables (structure only, no rows)\n";

    // The ledger came across with the schema; clear it so the module's own
    // migrations are seen as pending and actually run here.
    $pdo->exec('DELETE FROM migrations');
    $pdo->exec('DELETE FROM modules');
    $pdo->exec('DELETE FROM module_tenants');

    // The module's own tables may already exist in the copied schema (if it is
    // installed live) — drop them so its migrations create them fresh.
    $uninstallSql = $moduleDir . '/uninstall.sql';
    if (is_file($uninstallSql)) {
        $pdo->exec((string) file_get_contents($uninstallSql));
    }

    $moduleApplied = Migrator::run($moduleDir . '/migrations', "$code/");
    echo '  module migrations: ' . count($moduleApplied) . "\n";

    // -----------------------------------------------------------------------
    // Fixtures. Module code calls Auth::tenantId() / invoiceScopeUserIds(),
    // which read the session, so real user rows and a real session are needed.
    //
    //   1 — tenant A, signed in by default
    //   2 — tenant B, a *separate* owner (created_by NULL)
    //   3 — a sub-user of tenant A
    //
    // User 2 exists so a module can test the failure that matters most and is
    // invisible when testing by hand: a query missing its `ruler` filter,
    // which hands one customer another customer's rows. Switch tenants in a
    // test with $_SESSION['user_id'] = 2, and switch back in a finally block.
    // -----------------------------------------------------------------------
    $pdo->prepare('INSERT INTO users (id, name, email, role) VALUES (1, ?, ?, ?)')
        ->execute(['Tenant A', 'a@module.test', 'admin']);
    $pdo->prepare('INSERT INTO users (id, name, email, role) VALUES (2, ?, ?, ?)')
        ->execute(['Tenant B', 'b@module.test', 'admin']);
    $pdo->prepare('INSERT INTO users (id, name, email, role, created_by) VALUES (3, ?, ?, ?, 1)')
        ->execute(['Member of A', 'member@module.test', 'manager']);

    $_SESSION['user_id'] = 1;

    // Registers routes and hook listeners exactly as a request would, so a
    // test can assert on what the module put into Hooks.
    $router = new Router();
    ModuleRegistry::enable($code, 1);
    ModuleRegistry::boot($router);

    foreach (ModuleRegistry::failures() as $failedCode => $message) {
        throw new \RuntimeException("module $failedCode failed to load: $message");
    }
    echo "  module booted\n";

    // -----------------------------------------------------------------------
    // The module's own tests
    // -----------------------------------------------------------------------
    $testFiles = glob($moduleDir . '/tests/*_test.php') ?: [];
    if ($testFiles === []) {
        echo "\n  no tests found in $moduleDir/tests/ — add <name>_test.php files\n";
    }

    foreach ($testFiles as $file) {
        echo "\n" . basename($file) . "\n";
        require $file;
    }

    $t = $GLOBALS['ds_tests'];
    echo "\n" . str_repeat('-', 48) . "\n";
    echo "  passed: $t[pass]   failed: $t[fail]\n";
    foreach ($t['failures'] as $f) {
        echo "  FAIL: $f\n";
    }

    $exitCode = $t['fail'] === 0 && $testFiles !== [] ? 0 : 1;
} catch (\Throwable $e) {
    echo "\n  harness error: " . $e->getMessage() . "\n";
    echo '  ' . $e->getFile() . ':' . $e->getLine() . "\n";
} finally {
    if ($keep) {
        echo "  kept database: $testDb\n";
    } else {
        // Drop through the no-database connection: the pooled one is still
        // selected into the database being removed.
        $server->exec("DROP DATABASE IF EXISTS `$testDb`");
    }
}

exit($exitCode);

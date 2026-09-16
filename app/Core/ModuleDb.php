<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * The database handle a module gets (4.119). Rule 3 of the module concept:
 *
 *     read anywhere — write only to the tables you created.
 *
 * A module may SELECT from any table in this application (that is what lets
 * it build any report it likes over invoices, customers, products), but an
 * INSERT/UPDATE/DELETE/ALTER/DROP/TRUNCATE has to name one of the module's
 * own tables — the ones its migrations created, listed by the DROP TABLE lines
 * of its uninstall.sql (already the source of truth for the pre-uninstall
 * export, so nothing new for an author to declare). Anything else throws
 * before it reaches MySQL.
 *
 * What this is: a guard against the honest mistake — an author writing to a
 * core table without meaning to — and the line the test harness and the ZIP
 * validator enforce statically (module code must not reference App\Core\Db).
 *
 * What this is NOT: a sandbox. Module code runs in the same PHP process as
 * core and could call Db::conn() directly; nothing in PHP prevents that. A
 * real boundary would be one MySQL user per module with GRANTs, which needs
 * the app to hold DB-admin rights at runtime — a bigger risk than the one it
 * removes. Modules are trusted code, reviewed before install (/help/modules).
 */
final class ModuleDb
{
    /** @var array<string, self> */
    private static array $instances = [];

    /** @var list<string> lowercase */
    private array $owned;

    private function __construct(private readonly string $code)
    {
        $this->owned = ModuleRegistry::ownedTables($code);
    }

    public static function for(string $code): self
    {
        if (!ModuleRegistry::isValidCode($code)) {
            throw new \InvalidArgumentException("Invalid module code: $code");
        }

        return self::$instances[$code] ??= new self($code);
    }

    /** @return list<string> */
    public function ownedTables(): array
    {
        return $this->owned;
    }

    /**
     * Any read, over any table. Returns all rows.
     *
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        $head = self::head($sql);

        if (!preg_match('/^(SELECT|WITH|SHOW|DESCRIBE|EXPLAIN)\b/i', $head)) {
            throw new ModuleDbException($this->code, 'select() accepts read statements only (SELECT/WITH/SHOW/DESCRIBE/EXPLAIN)', $sql);
        }
        // A SELECT that writes: to a file on the server.
        if (preg_match('/\bINTO\s+(OUTFILE|DUMPFILE)\b/i', $sql)) {
            throw new ModuleDbException($this->code, 'SELECT ... INTO OUTFILE/DUMPFILE is not allowed', $sql);
        }

        $st = Db::conn()->prepare($sql);
        $st->execute($params);

        return $st->fetchAll();
    }

    /** First row of a select(), or null. */
    public function one(string $sql, array $params = []): ?array
    {
        return $this->select($sql, $params)[0] ?? null;
    }

    /**
     * A write — to one of this module's own tables only. Returns the affected
     * row count.
     */
    public function execute(string $sql, array $params = []): int
    {
        $table = $this->writeTarget($sql);

        if (!in_array($table, $this->owned, true)) {
            throw new ModuleDbException(
                $this->code,
                "write to `$table` refused — this module owns only: " . (implode(', ', $this->owned) ?: '(no tables; add DROP TABLE lines to uninstall.sql)'),
                $sql
            );
        }

        $st = Db::conn()->prepare($sql);
        $st->execute($params);

        return $st->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int) Db::conn()->lastInsertId();
    }

    /**
     * Runs $fn inside a transaction; commits if it returns, rolls back if it
     * throws. Nested calls join the outer transaction rather than starting
     * another (MySQL has no nested transactions — a second BEGIN would
     * silently commit the first).
     *
     * @template T
     * @param  callable(self): T $fn
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        $pdo = Db::conn();
        if ($pdo->inTransaction()) {
            return $fn($this);
        }

        $pdo->beginTransaction();
        try {
            $result = $fn($this);
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------

    /**
     * The one table a write statement targets, lowercased and unquoted.
     *
     * Multi-table writes (UPDATE a JOIN b, DELETE a FROM a JOIN b) are refused
     * outright rather than parsed: the honest version of "which tables does
     * this change" for those is a SQL parser, and a module that needs one can
     * write to one owned table at a time instead.
     */
    private function writeTarget(string $sql): string
    {
        $head = self::head($sql);

        $patterns = [
            '/^(?:INSERT|REPLACE)\s+(?:IGNORE\s+)?INTO\s+' . self::IDENT . '/i',
            '/^UPDATE\s+(?:LOW_PRIORITY\s+)?(?:IGNORE\s+)?' . self::IDENT . '/i',
            '/^DELETE\s+(?:LOW_PRIORITY\s+)?(?:QUICK\s+)?(?:IGNORE\s+)?FROM\s+' . self::IDENT . '/i',
            '/^ALTER\s+TABLE\s+' . self::IDENT . '/i',
            '/^TRUNCATE\s+(?:TABLE\s+)?' . self::IDENT . '/i',
            '/^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?' . self::IDENT . '/i',
            '/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?' . self::IDENT . '/i',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $head, $m)) {
                if (isset($m['schema']) && $m['schema'] !== '') {
                    throw new ModuleDbException($this->code, 'schema-qualified table names are not allowed', $sql);
                }
                if (preg_match('/^(UPDATE|DELETE)\b/i', $head) && preg_match('/\bJOIN\b/i', $sql)) {
                    throw new ModuleDbException($this->code, 'multi-table writes are not supported — write to one owned table at a time', $sql);
                }

                return strtolower($m['table']);
            }
        }

        throw new ModuleDbException(
            $this->code,
            'execute() accepts INSERT/REPLACE/UPDATE/DELETE/ALTER TABLE/TRUNCATE/DROP TABLE/CREATE TABLE on an owned table; use select() for reads',
            $sql
        );
    }

    /** `schema`.`table` or table — either part optionally backticked. */
    private const IDENT = '(?:`?(?<schema>[A-Za-z0-9_]+)`?\s*\.\s*)?`?(?<table>[A-Za-z0-9_]+)`?';

    /** The statement with leading whitespace and comments stripped, so the verb check can't be fooled by a comment in front. */
    private static function head(string $sql): string
    {
        $s = $sql;
        do {
            $before = $s;
            $s = preg_replace('#^\s*(/\*.*?\*/|--[^\n]*\n|\#[^\n]*\n)#s', '', $s) ?? $s;
        } while ($s !== $before);

        return ltrim($s);
    }
}

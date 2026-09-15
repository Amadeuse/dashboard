<?php

declare(strict_types=1);

namespace App\Modules\ModuleTemplate\Models;

use App\Core\Auth;
use App\Core\Db;

/**
 * Data access for this module's own table.
 *
 * The rule that matters most here: **every query filters on `ruler`**. A
 * module runs with the same database access core has, so nothing stops a
 * missing WHERE clause from returning another tenant's rows — this is the
 * single most common way a module leaks data. Take the tenant from
 * Auth::tenantId(), never from user input.
 */
final class TemplateItem
{
    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return Db::all(
            'SELECT * FROM template_items WHERE ruler = ? ORDER BY id DESC',
            [Auth::tenantId()]
        );
    }

    /** Null when the id doesn't exist *or* belongs to another tenant — the caller can't tell the two apart, which is the point. */
    public static function find(int $id): ?array
    {
        return Db::all(
            'SELECT * FROM template_items WHERE id = ? AND ruler = ?',
            [$id, Auth::tenantId()]
        )[0] ?? null;
    }

    /** @param array<string,string> $data  @return array<string,string> field => error */
    public static function validate(array $data): array
    {
        $errors = [];

        $label = trim($data['label'] ?? '');
        if ($label === '') {
            $errors['label'] = t('tpl.err_label_required');
        } elseif (mb_strlen($label) > 255) {
            $errors['label'] = t('tpl.err_label_long');
        }

        return $errors;
    }

    public static function save(array $data, ?int $id = null): int
    {
        $ruler = Auth::tenantId();
        $pdo   = Db::conn();

        if ($id === null) {
            $pdo->prepare('INSERT INTO template_items (ruler, label, note) VALUES (?, ?, ?)')
                ->execute([$ruler, $data['label'], $data['note'] ?? null]);

            return (int) $pdo->lastInsertId();
        }

        // The ruler in the WHERE clause, not just the id: without it, a posted
        // id from another tenant would be updated happily.
        $pdo->prepare('UPDATE template_items SET label = ?, note = ? WHERE id = ? AND ruler = ?')
            ->execute([$data['label'], $data['note'] ?? null, $id, $ruler]);

        return $id;
    }

    public static function delete(int $id): void
    {
        Db::conn()->prepare('DELETE FROM template_items WHERE id = ? AND ruler = ?')
            ->execute([$id, Auth::tenantId()]);
    }
}

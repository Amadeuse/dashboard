<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;
use App\Core\Sms;

final class User
{
    public static function findByEmail(string $email): ?array
    {
        return Db::all('SELECT * FROM users WHERE email = ?', [$email])[0] ?? null;
    }

    public static function findById(int $id): ?array
    {
        return Db::all('SELECT * FROM users WHERE id = ?', [$id])[0] ?? null;
    }

    public static function findByGoogleId(string $googleId): ?array
    {
        return Db::all('SELECT * FROM users WHERE google_id = ?', [$googleId])[0] ?? null;
    }

    public static function findByPhone(string $phone): ?array
    {
        return Db::all('SELECT * FROM users WHERE phone = ?', [$phone])[0] ?? null;
    }

    public static function emailExists(string $email): bool
    {
        return self::findByEmail($email) !== null;
    }

    /**
     * The sub-users *this* tenant's admin created — /settings/users' roster.
     * Was unscoped (every user, every tenant) before multi-tenancy (migrations/
     * 022-025) existed; fixed alongside it, since an unscoped roster here meant
     * any admin could see — and, via updateSubUser(), edit — any other
     * tenant's sub-users, including their password. role != 'superadmin' is
     * belt-and-braces: created_by already excludes it (see ensureSuperuser()),
     * this just keeps it out even if that ever changes.
     */
    public static function all(int $ruler): array
    {
        return Db::all("SELECT * FROM users WHERE created_by = ? AND role != 'superadmin' ORDER BY name", [$ruler]);
    }

    /**
     * Every tenant (an admin with no creator of their own) plus their
     * sub-users, for /superuser's roster — the one place a cross-tenant,
     * unscoped listing is actually the point. Grouped in PHP, not SQL: the
     * table's small enough that a self-join/window-function query would be
     * more code for no real gain.
     *
     * @return array<int, array{tenant: array<string,mixed>, subUsers: array<int, array<string,mixed>>}>
     *   keyed by the tenant's own user id, tenant first then children, both
     *   ordered by name.
     */
    public static function allGroupedByTenant(): array
    {
        $rows = Db::all("SELECT * FROM users WHERE role != 'superadmin' ORDER BY name");

        $tenants = [];
        foreach ($rows as $row) {
            if ($row['created_by'] === null) {
                $tenants[(int) $row['id']] = ['tenant' => $row, 'subUsers' => []];
            }
        }
        foreach ($rows as $row) {
            if ($row['created_by'] !== null && isset($tenants[(int) $row['created_by']])) {
                $tenants[(int) $row['created_by']]['subUsers'][] = $row;
            }
        }

        return $tenants;
    }

    /**
     * The tenant admin's own id plus every sub-user they created — "the
     * whole team", used anywhere invoices need to be scoped per-tenant
     * rather than per-individual-user (invoices.created_by is a single user
     * id, not a ruler — see Dashboard.php and InvoiceController::orders()).
     * Always at least [$ruler].
     *
     * @return list<int>
     */
    public static function tenantMemberIds(int $ruler): array
    {
        return array_map('intval', array_column(
            Db::all('SELECT id FROM users WHERE id = ? OR created_by = ?', [$ruler, $ruler]),
            'id'
        ));
    }

    /** code => translated label, in a fixed display order (most to least access). Deliberately never includes 'superadmin' — see ensureSuperuser(). */
    public static function roles(): array
    {
        return [
            'admin'   => t('role.admin'),
            'manager' => t('role.manager'),
            'viewer'  => t('role.viewer'),
        ];
    }

    /**
     * Called only from Auth::attemptSuperuser() after the .env credential
     * itself already matched — this never authenticates anyone by itself,
     * it only materialises/refreshes a normal `users` row (role=superadmin,
     * created_by NULL) so the rest of the app has one to work with. The
     * hash is re-derived from the current .env password on every call, so
     * changing SUPERUSER_PASSWORD takes effect on the very next login with
     * no separate sync step.
     *
     * @return int the superadmin's user id
     */
    public static function ensureSuperuser(string $email, string $password): int
    {
        $conn = Db::conn();
        $conn->prepare(
            "INSERT INTO users (name, email, password_hash, role)
             VALUES ('SuperUser', ?, ?, 'superadmin')
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = 'superadmin'"
        )->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);

        return (int) (self::findByEmail($email)['id'] ?? 0);
    }

    public static function create(string $name, string $email, string $password, ?string $phone = null): int
    {
        // NULL, never '' — the column is UNIQUE, and two accounts with no phone
        // would otherwise collide on an empty string instead of being distinct NULLs.
        Db::conn()->prepare('INSERT INTO users (name, email, password_hash, phone) VALUES (?, ?, ?, ?)')
            ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone === '' ? null : $phone]);

        return (int) Db::conn()->lastInsertId();
    }

    /** No password — the account was created via "Continue with Google". */
    public static function createFromGoogle(string $name, string $email, string $googleId): int
    {
        Db::conn()->prepare('INSERT INTO users (name, email, google_id) VALUES (?, ?, ?)')
            ->execute([$name, $email, $googleId]);

        return (int) Db::conn()->lastInsertId();
    }

    /** Added by an admin from /settings/users, not self-registered. */
    public static function createSubUser(array $data, int $createdBy): int
    {
        Db::conn()->prepare(
            'INSERT INTO users (name, email, phone, avatar, role, password_hash, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $data['name'],
            $data['email'],
            $data['phone'] === '' ? null : $data['phone'],
            $data['avatar'] === '' ? null : $data['avatar'],
            $data['role'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $createdBy,
        ]);

        return (int) Db::conn()->lastInsertId();
    }

    /**
     * Editing an existing sub-user — password stays put unless a new one was
     * typed. `created_by = ?` in the WHERE means an admin can only ever edit
     * (and only ever *has* editing them tested against) sub-users they
     * themselves created — id alone isn't enough, that would let one
     * tenant's admin overwrite another tenant's sub-user (including their
     * password) just by guessing/finding the row id. Same reasoning as
     * Customer::update()'s `ruler = ?`.
     */
    public static function updateSubUser(int $id, array $data, int $ruler): void
    {
        $sql    = 'UPDATE users SET name = ?, email = ?, phone = ?, avatar = ?, role = ?';
        $params = [
            $data['name'],
            $data['email'],
            $data['phone'] === '' ? null : $data['phone'],
            $data['avatar'] === '' ? null : $data['avatar'],
            $data['role'],
        ];

        if ($data['password'] !== '') {
            $sql      .= ', password_hash = ?';
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $sql      .= ' WHERE id = ? AND created_by = ?';
        $params[] = $id;
        $params[] = $ruler;

        Db::conn()->prepare($sql)->execute($params);
    }

    /**
     * /settings/users' add/edit form. $editingId excludes that row from the
     * uniqueness checks (same reasoning as validateProfileUpdate) — null
     * when adding a new sub-user.
     *
     * @return array{0: array<string,string>, 1: array<string,string>} [clean, errors]
     */
    public static function validateSubUser(array $input, ?int $editingId): array
    {
        $clean = [
            'name'     => trim((string) ($input['name'] ?? '')),
            'email'    => trim((string) ($input['email'] ?? '')),
            'phone'    => trim((string) ($input['phone'] ?? '')),
            'role'     => (string) ($input['role'] ?? ''),
            'password' => (string) ($input['password'] ?? ''),
        ];
        $errors = [];

        if ($clean['name'] === '') {
            $errors['name'] = terr('auth.err_name_required');
        }

        if (!filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = terr('auth.err_email_invalid');
        } else {
            $existing = self::findByEmail($clean['email']);
            if ($existing !== null && (int) $existing['id'] !== $editingId) {
                $errors['email'] = terr('auth.err_email_taken');
            }
        }

        if ($clean['phone'] !== '') {
            $normalized = Sms::normalize($clean['phone']);
            if ($normalized === null) {
                $errors['phone'] = terr('auth.err_phone_invalid');
            } else {
                $existingPhone = self::findByPhone($normalized);
                if ($existingPhone !== null && (int) $existingPhone['id'] !== $editingId) {
                    $errors['phone'] = terr('profile.err_phone_taken');
                }
                $clean['phone'] = $normalized;
            }
        }

        if (!isset(self::roles()[$clean['role']])) {
            $errors['role'] = terr('users.err_role_required');
        }

        // Adding: a password is required. Editing: blank means "leave it as is".
        if (($editingId === null || $clean['password'] !== '') && strlen($clean['password']) < 8) {
            $errors['password'] = terr('auth.err_password_min');
        }

        return [$clean, $errors];
    }

    /** Attaches Google sign-in to an account that was originally created with a password. */
    public static function linkGoogleId(int $id, string $googleId): void
    {
        Db::conn()->prepare('UPDATE users SET google_id = ? WHERE id = ?')->execute([$googleId, $id]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        Db::conn()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function updateProfile(int $id, array $data): void
    {
        Db::conn()->prepare('UPDATE users SET name = ?, email = ?, phone = ?, avatar = ? WHERE id = ?')
            ->execute([
                $data['name'],
                $data['email'],
                $data['phone'] === '' ? null : $data['phone'],
                $data['avatar'] === '' ? null : $data['avatar'],
                $id,
            ]);
    }

    /** @return array{0: array<string,string>, 1: array<string,string>} [clean, errors] */
    public static function validateProfileUpdate(array $input, int $currentUserId): array
    {
        $clean = [
            'name'  => trim((string) ($input['name'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
        ];
        $errors = [];

        if ($clean['name'] === '') {
            $errors['name'] = terr('auth.err_name_required');
        }

        if (!filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = terr('auth.err_email_invalid');
        } else {
            $existing = self::findByEmail($clean['email']);
            if ($existing !== null && (int) $existing['id'] !== $currentUserId) {
                $errors['email'] = terr('auth.err_email_taken');
            }
        }

        if ($clean['phone'] !== '') {
            $normalized = Sms::normalize($clean['phone']);
            if ($normalized === null) {
                $errors['phone'] = terr('auth.err_phone_invalid');
            } else {
                $existingPhone = self::findByPhone($normalized);
                if ($existingPhone !== null && (int) $existingPhone['id'] !== $currentUserId) {
                    $errors['phone'] = terr('profile.err_phone_taken');
                }
                $clean['phone'] = $normalized;
            }
        }

        return [$clean, $errors];
    }

    /** @return array{0: array<string,string>, 1: array<string,string>} [clean, errors] */
    public static function validateRegistration(array $input): array
    {
        $clean = [
            'name'     => trim((string) ($input['name'] ?? '')),
            'email'    => trim((string) ($input['email'] ?? '')),
            'phone'    => trim((string) ($input['phone'] ?? '')),
            'password' => (string) ($input['password'] ?? ''),
        ];
        $confirm = (string) ($input['password_confirm'] ?? '');
        $errors  = [];

        if ($clean['name'] === '') {
            $errors['name'] = terr('auth.err_name_required');
        }

        if (!filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = terr('auth.err_email_invalid');
        } elseif (self::emailExists($clean['email'])) {
            $errors['email'] = terr('auth.err_email_taken');
        }

        // Optional — only used as a second OTP channel, so an empty value is fine.
        if ($clean['phone'] !== '') {
            $normalized = Sms::normalize($clean['phone']);
            if ($normalized === null) {
                $errors['phone'] = terr('auth.err_phone_invalid');
            } else {
                $clean['phone'] = $normalized;
            }
        }

        if (strlen($clean['password']) < 8) {
            $errors['password'] = terr('auth.err_password_min');
        } elseif ($clean['password'] !== $confirm) {
            $errors['password_confirm'] = terr('auth.err_password_mismatch');
        }

        return [$clean, $errors];
    }
}

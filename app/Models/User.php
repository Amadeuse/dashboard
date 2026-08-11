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

    /** Every user in the (single) organization — the /settings/users roster. */
    public static function all(): array
    {
        return Db::all('SELECT * FROM users ORDER BY name');
    }

    /** code => translated label, in a fixed display order (most to least access). */
    public static function roles(): array
    {
        return [
            'admin'   => t('role.admin'),
            'manager' => t('role.manager'),
            'viewer'  => t('role.viewer'),
        ];
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

    /** Editing an existing sub-user — password stays put unless a new one was typed. */
    public static function updateSubUser(int $id, array $data): void
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

        $sql      .= ' WHERE id = ?';
        $params[] = $id;

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

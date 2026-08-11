<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Session-based auth — one user id in $_SESSION, same session the rest of
 * the app already uses for CSRF/flash. No auth gate exists yet (deliberately
 * — see handoff.md): logging in only changes what the topbar shows, it does
 * not restrict any page.
 */
final class Auth
{
    private const REMEMBER_SECONDS = 60 * 60 * 24 * 30;

    public static function attempt(string $email, string $password, bool $remember = false): bool
    {
        $user = User::findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        self::login((int) $user['id'], $remember);
        return true;
    }

    /** $remember re-issues the session cookie with a 30-day lifetime instead of "until browser close". */
    public static function login(int $userId, bool $remember = false): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;

        if ($remember) {
            setcookie(session_name(), session_id(), [
                'expires'  => time() + self::REMEMBER_SECONDS,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        return isset($_SESSION['user_id']) ? User::findById((int) $_SESSION['user_id']) : null;
    }

    /** Page-specific gate (not an app-wide one, see class docblock) — no session → straight to /login. */
    public static function requireUser(): array
    {
        $user = self::user();
        if ($user === null) {
            redirect('/login');
        }

        return $user;
    }

    /** Same, plus role === 'admin' — the sub-users/organization settings pages. */
    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        if ($user['role'] !== 'admin') {
            flash('notice', t('error.forbidden'));
            redirect('/');
        }

        return $user;
    }
}

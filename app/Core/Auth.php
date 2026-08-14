<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Session-based auth — one user id in $_SESSION, same session the rest of
 * the app already uses for CSRF/flash. The app-wide gate (public/index.php)
 * redirects any unauthenticated request outside the auth pages to /login —
 * see handoff.md for the allow-list and why it lives in index.php, not here.
 *
 * Each visitor's session is a separate file keyed by their own session-id
 * cookie — $_SESSION here is never shared across browsers/users, so this
 * (and the idle timeout below) already works correctly with many concurrent
 * logged-in users, nothing extra needed for that.
 */
final class Auth
{
    private const REMEMBER_SECONDS = 60 * 60 * 24 * 30;

    /** Idle-timeout default (minutes) when SESSION_TIMEOUT_MINUTES isn't set in .env. */
    private const DEFAULT_TIMEOUT_MINUTES = 30;

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
        $_SESSION['user_id']      = $userId;
        $_SESSION['last_activity'] = time();

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
        unset($_SESSION['user_id'], $_SESSION['last_activity']);
        session_regenerate_id(true);
    }

    /**
     * True only for a session that both has a user id and hasn't sat idle
     * past SESSION_TIMEOUT_MINUTES (.env, default 30) — an expired session
     * is logged out right here so every caller (this class's own gates, the
     * app-wide one in index.php) sees the same "not logged in" state instead
     * of each having to re-check the timestamp itself.
     */
    public static function check(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        $timeoutSeconds = (int) env('SESSION_TIMEOUT_MINUTES', self::DEFAULT_TIMEOUT_MINUTES) * 60;
        $lastActivity   = $_SESSION['last_activity'] ?? null;

        if ($lastActivity !== null && time() - $lastActivity > $timeoutSeconds) {
            self::logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function user(): ?array
    {
        return self::check() ? User::findById((int) $_SESSION['user_id']) : null;
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

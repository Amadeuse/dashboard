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
        if (self::attemptSuperuser($email, $password, $remember)) {
            return true;
        }

        $user = User::findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        self::login((int) $user['id'], $remember);
        return true;
    }

    /**
     * The SuperUser's real credential source is .env (SUPERUSER_EMAIL/
     * SUPERUSER_PASSWORD), compared directly with hash_equals() — not
     * users.password_hash. A `users` row (role=superadmin) is created or
     * refreshed on every successful attempt purely so the rest of the app
     * (Auth::user(), the topbar, sessions) has a normal row to work with;
     * it is never the actual source of truth for the password. That split
     * is deliberate: a database-only compromise (leaked backup, SQL
     * injection) can't recover a working SuperUser credential from it, only
     * a .env leak can — and .env already holds DB_PASS/MAIL_PASSWORD in
     * plaintext, so this doesn't lower the bar the rest of the app already
     * set. Blank .env values never match (empty submitted password against
     * empty configured one is rejected explicitly), so the feature is off
     * by default.
     */
    private static function attemptSuperuser(string $email, string $password, bool $remember): bool
    {
        $configuredEmail    = (string) env('SUPERUSER_EMAIL', '');
        $configuredPassword = (string) env('SUPERUSER_PASSWORD', '');

        if ($configuredEmail === '' || $configuredPassword === '' || $password === '') {
            return false;
        }

        if (!hash_equals(strtolower($configuredEmail), strtolower($email)) || !hash_equals($configuredPassword, $password)) {
            return false;
        }

        $userId = User::ensureSuperuser($configuredEmail, $configuredPassword);
        self::login($userId, $remember);
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
        unset($_SESSION['user_id'], $_SESSION['last_activity'], $_SESSION['impersonating_tenant']);
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

    /**
     * The tenant every "ruler"-scoped row (customers, products, product_types,
     * product_warehouse, organization) is filtered/written against. A root
     * account (users.created_by NULL — an admin who registered themselves) is
     * its own tenant; a sub-user (created from /settings/users) shares the
     * tenant of whoever created them, not a separate one of their own — same
     * "one shared organization" model /settings/users already assumes (see
     * User::all()'s docblock).
     *
     * A superadmin has no tenant of their own — they only ever act as one by
     * impersonating it from /superuser (self::impersonate()). Calling this
     * without having picked one redirects to /superuser instead of resolving
     * a meaningless id, exactly like requireUser() redirects to /login — so
     * every existing controller that already calls tenantId() (customers,
     * products, organization, invoices) gets this "pick a tenant first" gate
     * for free, with no per-controller change needed.
     *
     * Non-nullable and self-gating like requireUser() — every caller is a
     * controller the app-wide gate (public/index.php) already only lets an
     * authenticated request reach, so this should never actually redirect
     * *there* in practice; it exists so callers get a plain int instead of
     * every one of them separately null-checking Auth::user().
     */
    public static function tenantId(): int
    {
        $user = self::requireUser();

        if ($user['role'] === 'superadmin') {
            $impersonating = $_SESSION['impersonating_tenant'] ?? null;
            if ($impersonating === null) {
                redirect('/superuser');
            }

            return (int) $impersonating;
        }

        return $user['created_by'] !== null ? (int) $user['created_by'] : (int) $user['id'];
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

    /**
     * Same, plus role === 'admin' — the sub-users/organization settings pages.
     * A superadmin currently impersonating a tenant also passes: every
     * impersonation target is a root tenant (impersonate() only accepts one
     * with created_by IS NULL), and every root tenant in this schema is
     * role='admin' by construction — so "impersonating" already implies
     * "impersonating an admin". A non-impersonating superadmin still fails
     * this exactly like anyone else: Auth::tenantId() would have already
     * bounced them to /superuser before they got this far in a controller
     * that also needs a ruler, but /settings/users and /settings/organization
     * both call requireAdmin() before tenantId(), so this check needs its
     * own impersonation awareness rather than relying on that ordering.
     */
    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        if ($user['role'] !== 'admin' && !($user['role'] === 'superadmin' && self::impersonating() !== null)) {
            flash('notice', t('error.forbidden'));
            redirect('/');
        }

        return $user;
    }

    /** /superuser and its two actions — role === 'superadmin' only, everyone else bounced to /. */
    public static function requireSuperuser(): array
    {
        $user = self::requireUser();
        if ($user['role'] !== 'superadmin') {
            flash('notice', t('error.forbidden'));
            redirect('/');
        }

        return $user;
    }

    /** "Browse as this tenant" — the caller (SuperUserController) has already checked requireSuperuser(). */
    public static function impersonate(int $tenantUserId): void
    {
        $_SESSION['impersonating_tenant'] = $tenantUserId;
    }

    public static function stopImpersonating(): void
    {
        unset($_SESSION['impersonating_tenant']);
    }

    /** Which tenant a superadmin is currently browsing as, or null if they haven't picked one yet. */
    public static function impersonating(): ?int
    {
        return isset($_SESSION['impersonating_tenant']) ? (int) $_SESSION['impersonating_tenant'] : null;
    }
}

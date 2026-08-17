-- Cross-tenant SuperUser account, credentials live in .env (SUPERUSER_EMAIL/
-- SUPERUSER_PASSWORD) — never assignable from /settings/users' own role
-- picker (User::roles() deliberately doesn't list it), only ever set by
-- App\Models\User::ensureSuperuser(), which Auth::attempt() calls on a
-- successful env-credential login. See handoff.md for the full design.
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('admin', 'manager', 'viewer', 'superadmin') NOT NULL DEFAULT 'admin';

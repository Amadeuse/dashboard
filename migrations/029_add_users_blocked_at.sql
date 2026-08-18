-- SuperUser-only user block/unblock. NULL = not blocked; a timestamp records
-- when SuperUser blocked them. Auth::check() force-logs-out a blocked user's
-- session on their very next request, and Auth::attempt() refuses a fresh
-- login for one — see App\Core\Auth.
ALTER TABLE `users`
  ADD COLUMN `blocked_at` TIMESTAMP NULL DEFAULT NULL AFTER `role`;

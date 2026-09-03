ALTER TABLE `users`
  ADD COLUMN `color` CHAR(7) NULL AFTER `avatar`;

-- Backfill: same 8-color palette User::PALETTE uses for new registrations,
-- assigned per-tenant (root first, then sub-users by name — the same
-- display order Dashboard::revenueByUser() already sorts by) so existing
-- teams get the same distinct-per-member colors a brand-new tenant would.
-- superadmin rows are left NULL — they have no team chart of their own.
UPDATE `users` u
JOIN (
  SELECT id,
         ROW_NUMBER() OVER (
           PARTITION BY COALESCE(created_by, id)
           ORDER BY (created_by IS NOT NULL), name
         ) AS rn
    FROM `users`
   WHERE role != 'superadmin'
) ranked ON ranked.id = u.id
SET u.color = ELT(((ranked.rn - 1) % 8) + 1,
  '#4f46e5', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4', '#a855f7', '#ec4899', '#84cc16')
WHERE u.role != 'superadmin';

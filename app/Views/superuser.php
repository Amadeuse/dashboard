<?php
/**
 * @var array<int, array{tenant: array<string,mixed>, subUsers: array<int, array<string,mixed>>}> $tenants
 *   User::allGroupedByTenant() — every root tenant plus their sub-users.
 * @var ?int $impersonating current impersonation target, if any (the global
 *   banner in layout.php already shows/handles this — this page doesn't
 *   repeat it, just highlights the matching row below).
 */
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active">
          <?= t('superuser.title') ?>
          <span class="badge bg-primary-subtle text-primary rounded-pill align-middle ms-1"><?= count($tenants) ?></span>
        </li>
      </ol>
    </nav>
  </div>
</div>

<div class="card ds-card">
  <?php if ($tenants === []): ?>
    <div class="card-body text-center text-secondary py-5">
      <i class="bi bi-people d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
      <?= t('superuser.empty') ?>
    </div>
  <?php else: ?>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="text-secondary">
            <th><?= t('auth.fullName') ?></th>
            <th><?= t('auth.email') ?></th>
            <th><?= t('superuser.subusers') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tenants as $tenantId => $group): $tenant = $group['tenant']; ?>
          <tr class="<?= $impersonating === $tenantId ? 'table-primary' : '' ?>">
            <td>
              <?= e($tenant['name']) ?>
              <?php if ($impersonating === $tenantId): ?>
                <span class="badge bg-primary rounded-pill ms-1"><?= t('superuser.currently_browsing') ?></span>
              <?php endif; ?>
              <?php if ($tenant['blocked_at'] !== null): ?>
                <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill ms-1"><?= t('superuser.blocked_badge') ?></span>
              <?php endif; ?>
            </td>
            <td><a href="mailto:<?= e($tenant['email']) ?>" class="text-decoration-none"><?= e($tenant['email']) ?></a></td>
            <td>
              <?php if ($group['subUsers'] === []): ?>
                <span class="text-secondary">—</span>
              <?php else: ?>
                <?php foreach ($group['subUsers'] as $sub): $subBlocked = $sub['blocked_at'] !== null; ?>
                  <form method="post" action="/superuser/toggle-block" class="d-inline m-0"
                        title="<?= e($subBlocked ? t('superuser.unblock') : t('superuser.block')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $sub['id'] ?>">
                    <button type="submit" class="badge border-0 rounded-pill me-1 <?= $subBlocked ? 'bg-danger-subtle text-danger-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                      <?php if ($subBlocked): ?><i class="bi bi-lock-fill me-1"></i><?php endif; ?><?= e($sub['name']) ?>
                    </button>
                  </form>
                <?php endforeach; ?>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <form method="post" action="/superuser/impersonate" class="d-inline m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="tenant_id" value="<?= (int) $tenantId ?>">
                <button type="submit" class="btn btn-sm <?= $impersonating === $tenantId ? 'btn-primary' : 'btn-outline-primary' ?>">
                  <i class="bi bi-eye me-1"></i><?= t('superuser.browse_as') ?>
                </button>
              </form>
              <form method="post" action="/superuser/toggle-block" class="d-inline m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $tenantId ?>">
                <button type="submit" class="btn btn-sm <?= $tenant['blocked_at'] !== null ? 'btn-success' : 'btn-outline-danger' ?>">
                  <i class="bi <?= $tenant['blocked_at'] !== null ? 'bi-unlock' : 'bi-lock' ?> me-1"></i>
                  <?= $tenant['blocked_at'] !== null ? t('superuser.unblock') : t('superuser.block') ?>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

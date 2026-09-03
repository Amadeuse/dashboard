<?php
/**
 * @var array<int, array{tenant: array<string,mixed>, subUsers: array<int, array<string,mixed>>}> $tenants
 *   User::allGroupedByTenant() — every root tenant plus their sub-users.
 * @var ?int $impersonating current impersonation target (root tenant), if
 *   any (the global banner in layout.php already shows/handles this — this
 *   page doesn't repeat it, just highlights the matching row below).
 * @var ?int $impersonatingUser the specific person picked (4.86) — the
 *   tenant itself, or one particular sub-user; differs from $impersonating
 *   only in the latter case. Drives each individual button's active state,
 *   where $impersonating drives the whole row's highlight.
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
      <table class="table table-hover table-striped align-middle mb-0">
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
              <?php if ($tenant['color'] !== null): ?><span class="ds-color-dot" style="background:<?= e($tenant['color']) ?>"></span><?php endif; ?>
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
                <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill"><?= count($group['subUsers']) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <form method="post" action="/superuser/impersonate" class="d-inline m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $tenantId ?>">
                <button type="submit" class="btn btn-sm <?= $impersonatingUser === $tenantId ? 'btn-primary' : 'btn-outline-primary' ?>">
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
          <?php if ($group['subUsers'] !== []): ?>
          <!-- Plain <details>, no JS/Bootstrap-collapse — same trick as
               sidebar.php's nav groups (see design-system.css's
               .ds-details-caret). A separate row/toggle, not the tenant's
               own "დათვალიერება" button — that stays a direct one-click
               impersonate so admins with sub-users don't lose the one-click
               path. Each sub-user row's own "დათვალიერება" now submits its
               OWN id (4.86) — SuperUserController::impersonate() resolves
               the root tenant from it either way (ruler-scoped data:
               customers/products/organization is always the root's), but
               narrows invoice-scoped views (orders.php, the dashboard) to
               just this one person — see Auth::invoiceScopeUserIds(). -->
          <tr>
            <td colspan="4" class="p-0 border-0">
              <details class="ds-subusers">
                <summary class="px-3 py-2 d-flex align-items-center gap-2 text-secondary small">
                  <i class="bi bi-chevron-down ds-details-caret"></i>
                  <?= count($group['subUsers']) ?> <?= t('superuser.subusers') ?>
                </summary>
                <div class="table-responsive bg-body-tertiary">
                  <table class="table table-sm align-middle mb-0">
                    <tbody>
                      <?php foreach ($group['subUsers'] as $sub): $subBlocked = $sub['blocked_at'] !== null; ?>
                      <tr>
                        <td class="ps-4">
                          <i class="bi bi-arrow-return-right text-secondary me-1"></i>
                          <?php if ($sub['color'] !== null): ?><span class="ds-color-dot" style="background:<?= e($sub['color']) ?>"></span><?php endif; ?>
                          <?= e($sub['name']) ?>
                          <?php if ($subBlocked): ?>
                            <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill ms-1"><?= t('superuser.blocked_badge') ?></span>
                          <?php endif; ?>
                        </td>
                        <td><a href="mailto:<?= e($sub['email']) ?>" class="text-decoration-none"><?= e($sub['email']) ?></a></td>
                        <td></td>
                        <td class="text-end">
                          <form method="post" action="/superuser/impersonate" class="d-inline m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $sub['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $impersonatingUser === (int) $sub['id'] ? 'btn-primary' : 'btn-outline-primary' ?>">
                              <i class="bi bi-eye me-1"></i><?= t('superuser.browse_as') ?>
                            </button>
                          </form>
                          <form method="post" action="/superuser/toggle-block" class="d-inline m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $sub['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $subBlocked ? 'btn-success' : 'btn-outline-danger' ?>">
                              <i class="bi <?= $subBlocked ? 'bi-unlock' : 'bi-lock' ?> me-1"></i>
                              <?= $subBlocked ? t('superuser.unblock') : t('superuser.block') ?>
                            </button>
                          </form>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </details>
            </td>
          </tr>
          <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

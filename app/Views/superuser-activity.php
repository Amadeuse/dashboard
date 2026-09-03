<?php
/**
 * @var array<int, array{tenant: array<string,mixed>, subUsers: array<int, array<string,mixed>>}> $tenantGroups
 *   User::allGroupedByTenant() — every root tenant plus their sub-users, the
 *   left-hand picker; sub-users render indented under their own admin so
 *   it's clear whose team each one belongs to (same grouping superuser.php
 *   already shows, 4.85).
 * @var ?int $selectedUserId the ?user_id= currently filtering the log, or
 *   null for "everyone".
 * @var array<int, array<string,mixed>> $entries ActivityLog::all() — newest
 *   first, user_name/user_color already joined in.
 *
 * Two-pane layout the user asked for verbatim: a list box of registered
 * users on the left, the activity log as a table (date/action/...) on the
 * right, filterable by clicking a name on the left (?user_id=N, a plain
 * link — no JS needed for the filter itself, only for the left list's own
 * client-side name search).
 */
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item"><a href="/superuser" class="text-decoration-none"><?= t('superuser.title') ?></a></li>
        <li class="breadcrumb-item active">
          <?= t('superuser.activity_title') ?>
          <span class="badge bg-primary-subtle text-primary rounded-pill align-middle ms-1"><?= count($entries) ?></span>
        </li>
      </ol>
    </nav>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-3">
    <div class="card ds-card">
      <div class="card-body p-2">
        <input type="search" class="form-control form-control-sm mb-2" id="activityUserFilter" placeholder="<?= t('table.search') ?>">
        <div class="list-group list-group-flush" style="max-height:70vh;overflow-y:auto;" id="activityUserList">
          <a href="/superuser/activity" class="list-group-item list-group-item-action <?= $selectedUserId === null ? 'active' : '' ?>">
            <?= t('superuser.activity_all_users') ?>
          </a>
          <?php foreach ($tenantGroups as $group): $tenant = $group['tenant']; $tid = (int) $tenant['id']; ?>
            <a href="/superuser/activity?user_id=<?= $tid ?>"
               class="list-group-item list-group-item-action d-flex align-items-center gap-2 <?= $selectedUserId === $tid ? 'active' : '' ?>"
               data-name="<?= e(mb_strtolower($tenant['name'])) ?>">
              <?php if ($tenant['color'] !== null): ?><span class="ds-color-dot" style="background:<?= e($tenant['color']) ?>"></span><?php endif; ?>
              <?= e($tenant['name']) ?>
            </a>
            <?php foreach ($group['subUsers'] as $sub): $sid = (int) $sub['id']; ?>
              <a href="/superuser/activity?user_id=<?= $sid ?>"
                 class="list-group-item list-group-item-action d-flex align-items-center gap-2 ps-4 <?= $selectedUserId === $sid ? 'active' : '' ?>"
                 data-name="<?= e(mb_strtolower($sub['name'])) ?>">
                <i class="bi bi-arrow-return-right text-secondary"></i>
                <?php if ($sub['color'] !== null): ?><span class="ds-color-dot" style="background:<?= e($sub['color']) ?>"></span><?php endif; ?>
                <?= e($sub['name']) ?>
              </a>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-9">
    <div class="card ds-card">
      <div class="card-body">
        <div class="ds-table" data-ds-table data-per-page="25" data-per-page-options="25,50,100">
          <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
              <thead>
                <tr class="text-secondary">
                  <th><?= t('superuser.activity_col_date') ?></th>
                  <th><?= t('superuser.activity_col_user') ?></th>
                  <th><?= t('superuser.activity_col_action') ?></th>
                  <th><?= t('superuser.activity_col_ip') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($entries as $row): ?>
                <tr>
                  <td class="text-secondary" data-order="<?= e($row['created_at']) ?>">
                    <?= ds_date(substr($row['created_at'], 0, 10)) ?> <?= substr($row['created_at'], 11, 8) ?>
                  </td>
                  <td>
                    <?php if ($row['user_color'] !== null): ?><span class="ds-color-dot" style="background:<?= e($row['user_color']) ?>"></span><?php endif; ?>
                    <?= e($row['user_name']) ?>
                  </td>
                  <td><?= e(\App\Core\ActivityLog::describe($row['method'], $row['path'])) ?></td>
                  <td class="text-secondary"><?= e((string) ($row['ip_address'] ?? '—')) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($entries === []): ?>
                <tr><td colspan="4" class="text-center text-secondary py-4"><?= t('superuser.activity_empty') ?></td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$scripts = ds_table_script() . <<<'HTML'
<script>
  document.getElementById('activityUserFilter').addEventListener('input', (event) => {
    const q = event.target.value.trim().toLowerCase();
    document.querySelectorAll('#activityUserList a[data-name]').forEach((row) => {
      row.hidden = q !== '' && !row.dataset.name.includes(q);
    });
  });
</script>
HTML;
?>

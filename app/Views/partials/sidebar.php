<div class="offcanvas-lg offcanvas-start ds-sidebar" tabindex="-1" id="sidebar">
  <div class="ds-sidebar-inner">
    <div class="ds-brand-row d-flex align-items-center justify-content-between">
      <a href="/" class="ds-brand">
        <span class="ds-brand-mark"><?= e(mb_substr(app_name(), 0, 1)) ?></span>
        <span>
          <span class="ds-brand-name d-block"><?= e(app_name()) ?></span>
          <span class="ds-brand-meta">v<?= e(app_version()) ?> · 2026-07-26</span>
        </span>
      </a>
      <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
    </div>

    <!-- Rendered from app/config/menu.json — edit the JSON, not this file. -->
    <nav class="ds-nav">
      <?php foreach (ds_menu() as $group): ?>
        <p class="ds-nav-section"><?= t($group['section']) ?></p>

        <?php foreach ($group['items'] as $item): ?>
          <?php $children = $item['children'] ?? []; ?>

          <?php if (!$children): ?>
            <a href="<?= e($item['url']) ?>" class="ds-nav-link <?= ds_is_current($item['url']) ? 'active' : '' ?>">
              <i class="bi <?= e($item['icon']) ?>"></i> <?= t($item['label']) ?>
            </a>
          <?php else: ?>
            <?php // <details> gives open/close for free — no JS, no Bootstrap collapse ids.
                  // Shared name= makes the group an exclusive accordion: opening one
                  // closes the previous. Stays open when a child is the current route.
                  $open = array_filter($children, fn(array $c): bool => ds_is_current($c['url'])); ?>
            <details class="ds-nav-group" name="ds-nav"<?= $open ? ' open' : '' ?>>
              <summary class="ds-nav-link">
                <i class="bi <?= e($item['icon']) ?>"></i> <?= t($item['label']) ?>
                <i class="bi bi-chevron-down ds-nav-caret"></i>
              </summary>
              <?php foreach ($children as $child): ?>
                <a href="<?= e($child['url']) ?>" class="ds-nav-link ds-nav-sublink <?= ds_is_current($child['url']) ? 'active' : '' ?>">
                  <?= t($child['label']) ?>
                </a>
              <?php endforeach; ?>
            </details>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
  </div>
</div>

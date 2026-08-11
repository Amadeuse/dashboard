<?php
/**
 * @var string $flagIdSuffix  unique per inclusion on a page — the <svg> defs use
 *                            id-referenced <use>, so two copies on one page would
 *                            collide without this (e.g. "topbar" vs "card").
 */
$crossId = 'ds-cross-' . $flagIdSuffix;
$ukId    = 'ds-uk-' . $flagIdSuffix;
?>
<?php if (ds_lang() === 'ka'): ?>
  <svg class="ds-flag" viewBox="0 0 900 600" width="21" height="14" aria-hidden="true">
    <defs>
      <path id="<?= $crossId ?>" d="M-60-20h40v-40h40v40h40v40h-40v40h-40v-40h-40z"/>
    </defs>
    <rect width="900" height="600" fill="#fff"/>
    <g fill="#ff0000">
      <rect x="390" width="120" height="600"/>
      <rect y="240" width="900" height="120"/>
      <use href="#<?= $crossId ?>" x="195" y="120"/>
      <use href="#<?= $crossId ?>" x="705" y="120"/>
      <use href="#<?= $crossId ?>" x="195" y="480"/>
      <use href="#<?= $crossId ?>" x="705" y="480"/>
    </g>
  </svg>
<?php else: ?>
  <svg class="ds-flag" viewBox="0 0 60 30" width="21" height="14" preserveAspectRatio="none" aria-hidden="true">
    <clipPath id="<?= $ukId ?>"><path d="M0 0v30h60V0z"/></clipPath>
    <rect width="60" height="30" fill="#012169"/>
    <path d="M0 0l60 30M60 0L0 30" stroke="#fff" stroke-width="6"/>
    <path d="M0 0l60 30M60 0L0 30" stroke="#c8102e" stroke-width="4" clip-path="url(#<?= $ukId ?>)"/>
    <path d="M30 0v30M0 15h60" stroke="#fff" stroke-width="10"/>
    <path d="M30 0v30M0 15h60" stroke="#c8102e" stroke-width="6"/>
  </svg>
<?php endif; ?>

<!doctype html>
<html lang="<?= ds_lang() ?>" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Nova Dashboard') ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Georgian:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/fonts/bpg-arial-caps/css/bpg-arial-caps.min.css" rel="stylesheet">
  <link href="/assets/css/design-system.css" rel="stylesheet">
  <link href="/vendor/floating-label/css/floating-label.css" rel="stylesheet">
  <link href="/assets/css/auth.css" rel="stylesheet">
</head>
<body>

<main class="ds-auth-shell">
  <div class="ds-auth-visual">
    <img class="ds-auth-visual-photo" alt="" aria-hidden="true">
    <div class="ds-auth-visual-scrim"></div>
    <div class="ds-auth-visual-caption">
      <p class="ds-auth-visual-tagline"><?= t('auth.visual.tagline') ?></p>
      <a class="ds-auth-visual-credit" id="auth-photo-credit" href="https://pixabay.com/images/search/finance/" target="_blank" rel="noopener" hidden>
        <?= t('auth.visual.photoBy') ?> <span id="auth-photo-credit-name"></span> · Pixabay
      </a>
    </div>
  </div>

  <div class="ds-auth-main">
    <?= $content ?>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/vendor/floating-label/js/floating-label.js"></script>
<script>
(() => {
  const photo = document.querySelector('.ds-auth-visual-photo');
  if (!photo || !window.matchMedia('(min-width: 901px)').matches) return; // hidden on mobile — don't burn API quota there

  const creditLink = document.getElementById('auth-photo-credit');
  const creditName = document.getElementById('auth-photo-credit-name');

  const loadNext = () => {
    fetch('/auth/photo')
      .then((r) => r.json())
      .then((data) => {
        if (!data.url) return;
        const preload = new Image();
        preload.onload = () => {
          photo.classList.remove('is-visible');
          setTimeout(() => {
            photo.src = data.url;
            photo.classList.add('is-visible');
            if (data.photographerName) {
              creditName.textContent = data.photographerName;
              creditLink.href = data.photographerUrl || 'https://pixabay.com';
              creditLink.hidden = false;
            }
          }, 400); // let the fade-out finish before swapping the src
        };
        preload.src = data.url;
      })
      .catch(() => {});
  };

  const scheduleNext = () => {
    setTimeout(() => { loadNext(); scheduleNext(); }, 10000 + Math.random() * 5000);
  };

  loadNext();
  scheduleNext();
})();
</script>
<?= $scripts ?? '' ?>
</body>
</html>

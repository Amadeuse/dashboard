<?php
/**
 * @var array   $errors  field => message from a failed password/OTP submit
 * @var array   $old     field => value ('email', and 'tab' when the OTP panel should stay open)
 * @var ?string $notice  e.g. "password changed — log in with your new password"
 */
$activeTab = ($old['tab'] ?? 'password') === 'otp' ? 'otp' : 'password';
$val = static fn(string $f): string => e((string) ($old[$f] ?? ''));
?>

<div class="card ds-card ds-auth-card">
  <div class="ds-auth-card-header">
    <a class="ds-auth-brand" href="/">
      <span class="ds-avatar ds-avatar-soft">N</span>
      <span><?= e(app_name()) ?></span>
    </a>
    <?php $otherLang = ds_lang() === 'ka' ? 'en' : 'ka'; $flagIdSuffix = 'login-card'; ?>
    <a class="ds-icon-btn" href="<?= ds_lang_url($otherLang) ?>" title="<?= t('topbar.language') ?>" aria-label="<?= t('topbar.language') ?>">
      <?php require APP_PATH . '/Views/partials/lang-flag.php'; ?>
    </a>
  </div>

  <div class="ds-auth-tabs" role="tablist" id="auth-tabs">
    <button class="ds-auth-tab <?= $activeTab === 'password' ? 'active' : '' ?>" type="button" role="tab" data-tab="password"><?= t('auth.tab.password') ?></button>
    <button class="ds-auth-tab <?= $activeTab === 'otp' ? 'active' : '' ?>" type="button" role="tab" data-tab="otp"><?= t('auth.tab.otp') ?></button>
  </div>

  <?php if ($notice !== null): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-0"><i class="bi bi-check-circle-fill"></i> <?= e($notice) ?></div>
  <?php endif; ?>

  <form class="ds-auth-panel" data-panel="password" method="post" action="/login" novalidate <?= $activeTab === 'otp' ? 'hidden' : '' ?>>
    <?= csrf_field() ?>
    <div>
      <h1 class="ds-auth-title"><?= t('auth.login.title') ?></h1>
      <p class="ds-auth-subtitle"><?= t('auth.login.subtitle') ?></p>
    </div>

    <a class="btn btn-outline-secondary ds-auth-google-btn" href="/auth/google">
      <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
        <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/>
        <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/>
        <path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/>
        <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/>
      </svg>
      <span><?= t('auth.google.login') ?></span>
    </a>
    <div class="ds-auth-divider"><span><?= t('auth.or') ?></span></div>

    <div class="form-floating">
      <input id="li-email" name="email" type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= $val('email') ?>" placeholder=" " autocomplete="email" required>
      <label for="li-email"><?= t('auth.email') ?></label>
      <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
    </div>
    <?php if (isset($errors['email'])): ?><div class="invalid-feedback d-block"><?= e($errors['email']) ?></div><?php endif; ?>

    <div class="form-floating form-floating--password">
      <input id="li-password" name="password" type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" placeholder=" " autocomplete="current-password" required>
      <label for="li-password"><?= t('auth.password') ?></label>
      <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
      <button type="button" class="btn-toggle-password" aria-label="<?= t('auth.show_password') ?>"
              data-show-label="<?= t('auth.show_password') ?>" data-hide-label="<?= t('auth.hide_password') ?>">
        <i class="bi bi-eye"></i>
      </button>
    </div>
    <?php if (isset($errors['password'])): ?><div class="invalid-feedback d-block"><?= e($errors['password']) ?></div><?php endif; ?>

    <div class="ds-auth-row">
      <div class="form-check d-flex align-items-center gap-2 mb-0">
        <input class="form-check-input mt-0" type="checkbox" name="remember" id="li-remember" checked>
        <label class="form-check-label" for="li-remember"><?= t('auth.rememberMe') ?></label>
      </div>
      <a class="ds-auth-link" href="/forgot-password"><?= t('auth.forgotPassword') ?></a>
    </div>

    <button class="btn btn-primary ds-auth-submit" type="submit"><?= t('auth.login.submit') ?></button>
  </form>

  <form class="ds-auth-panel" data-panel="otp" method="post" action="/login/otp/verify" novalidate <?= $activeTab === 'password' ? 'hidden' : '' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="channel" id="otp-channel">
    <input type="hidden" name="identity" id="otp-identity-combined">
    <input type="hidden" name="code" id="otp-code-combined">
    <div>
      <h1 class="ds-auth-title"><?= t('auth.otp.title') ?></h1>
      <p class="ds-auth-subtitle"><?= t('auth.otp.subtitle') ?></p>
    </div>

    <?php if (isset($errors['otp'])): ?><div class="invalid-feedback d-block"><?= e($errors['otp']) ?></div><?php endif; ?>

    <div class="ds-auth-tabs" role="tablist" id="otp-channels">
      <button type="button" class="ds-auth-tab active" data-channel="email"><i class="bi bi-envelope me-1"></i><?= t('auth.otp.channel_email') ?></button>
      <button type="button" class="ds-auth-tab" data-channel="sms"><i class="bi bi-phone me-1"></i><?= t('auth.otp.channel_sms') ?></button>
    </div>

    <div class="form-floating" data-channel-field="email">
      <input id="otp-email" type="email" class="form-control" placeholder=" " autocomplete="email">
      <label for="otp-email"><?= t('auth.email') ?></label>
      <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
    </div>
    <div class="form-floating" data-channel-field="sms" hidden>
      <input id="otp-phone" type="tel" class="form-control" placeholder=" " autocomplete="tel">
      <label for="otp-phone"><?= t('auth.phone') ?></label>
      <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
    </div>

    <button class="btn btn-outline-primary" type="button" id="otp-send"><?= t('auth.otp.sendCode') ?></button>
    <div class="small text-secondary" id="otp-status"></div>

    <div class="ds-auth-otp-field">
      <label class="small fw-semibold"><?= t('auth.otp.enterCode') ?></label>
      <div class="ds-auth-otp-row" aria-label="6-digit verification code">
        <?php for ($i = 1; $i <= 6; $i++): ?>
          <input class="ds-auth-otp-box" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="<?= $i ?>">
        <?php endfor; ?>
      </div>
    </div>

    <button class="btn btn-primary ds-auth-submit" type="submit"><?= t('auth.otp.submit') ?></button>
  </form>

  <p class="ds-auth-footer"><?= t('auth.login.noAccount') ?> <a class="ds-auth-link" href="/register"><?= t('auth.login.signUp') ?></a></p>
</div>

<?php
$labels = json_encode([
    'sent'       => t('auth.otp.sent'),
    'enterFirst' => t('auth.err_email_required'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$scripts = "<script>window.dsOtpLabels = $labels;</script>\n" . <<<'HTML'
<script>
(() => {
  const tabs = document.getElementById('auth-tabs').querySelectorAll('.ds-auth-tab');
  const panels = document.querySelectorAll('.ds-auth-panel');
  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      tabs.forEach((t) => t.classList.toggle('active', t === tab));
      panels.forEach((p) => { p.hidden = p.dataset.panel !== tab.dataset.tab; });
    });
  });

  // OTP channel switch (email vs SMS) — separate tab strip, inside the OTP panel.
  const channelTabs = document.querySelectorAll('#otp-channels .ds-auth-tab');
  const channelFields = document.querySelectorAll('[data-channel-field]');
  let channel = 'email';
  channelTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      channel = tab.dataset.channel;
      channelTabs.forEach((t) => t.classList.toggle('active', t === tab));
      channelFields.forEach((f) => { f.hidden = f.dataset.channelField !== channel; });
    });
  });

  const boxes = document.querySelectorAll('.ds-auth-otp-box');
  boxes.forEach((box, i) => {
    box.addEventListener('input', () => {
      box.value = box.value.replace(/\D/g, '').slice(0, 1);
      if (box.value && boxes[i + 1]) boxes[i + 1].focus();
    });
    box.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !box.value && boxes[i - 1]) boxes[i - 1].focus();
    });
    box.addEventListener('paste', (e) => {
      const digits = (e.clipboardData.getData('text').match(/\d/g) || []).slice(0, 6);
      if (!digits.length) return;
      e.preventDefault();
      digits.forEach((d, j) => { if (boxes[j]) boxes[j].value = d; });
      (boxes[digits.length - 1] || boxes[boxes.length - 1]).focus();
    });
  });

  const identityValue = () => (channel === 'sms'
    ? document.getElementById('otp-phone').value.trim()
    : document.getElementById('otp-email').value.trim());

  const otpForm = document.querySelector('form[data-panel="otp"]');
  otpForm.addEventListener('submit', () => {
    document.getElementById('otp-channel').value = channel;
    document.getElementById('otp-identity-combined').value = identityValue();
    document.getElementById('otp-code-combined').value = Array.from(boxes).map((b) => b.value).join('');
  });

  const sendBtn = document.getElementById('otp-send');
  const status = document.getElementById('otp-status');
  sendBtn.addEventListener('click', () => {
    const identity = identityValue();
    if (!identity) { status.textContent = window.dsOtpLabels.enterFirst; return; }

    sendBtn.disabled = true;
    status.textContent = '';
    fetch('/login/otp/send', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ channel, identity, _token: otpForm.querySelector('input[name=_token]').value }),
    })
      .then((r) => r.json())
      .then((data) => { status.textContent = data.ok ? window.dsOtpLabels.sent : data.error; })
      .finally(() => { sendBtn.disabled = false; });
  });
})();
</script>
HTML;
?>

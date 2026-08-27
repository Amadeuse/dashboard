<?php
/**
 * @var string $message the sender's own typed message (invoiceEmailModal's
 *   textarea) — plain text, line breaks preserved via nl2br
 * @var array  $org     the organization row (Organization::get()) — name/
 *   phone/email/address for the signature block below the message
 *
 * The actual HTML body InvoiceController::sendEmail() sends — a real,
 * standalone template (rendered via Controller::renderToString(
 * 'emails/invoice', ...)) instead of the two-line string it used to build
 * inline, so it's a normal file to open and restyle, same as any other view
 * (4.66 in handoff.md). Table layout + inline styles only, no <style>
 * block and no external assets — that's still the only markup every major
 * email client (Outlook desktop included) renders reliably.
 */
$signatureLines = array_filter([
    (string) ($org['name'] ?? ''),
    (string) ($org['phone'] ?? ''),
    (string) ($org['email'] ?? ''),
    (string) ($org['address'] ?? ''),
], static fn(string $v): bool => $v !== '');
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0; padding:0; background:#f3f4f6;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden; font-family:Tahoma,Arial,sans-serif; color:#1a1a1a;">
          <tr>
            <td style="background:#2563eb; padding:20px 28px;">
              <span style="color:#ffffff; font-size:16px; font-weight:bold;"><?= e((string) ($org['name'] ?? app_name())) ?></span>
            </td>
          </tr>
          <tr>
            <td style="padding:28px; color:red; font-size:12px; line-height:1.6;">
              <?= nl2br(e($message)) ?>
            </td>
          </tr>
          <?php if ($signatureLines !== []): ?>
          <tr>
            <td style="padding:0 28px 28px; font-size:13px; color:#555;">
              <div style="border-top:1px solid #eee; padding-top:16px;">
                <?= implode('<br>', array_map('e', $signatureLines)) ?>
              </div>
            </td>
          </tr>
          <?php endif; ?>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>

<?php

declare(strict_types=1);

/**
 * Toast-notification catalog — code => [type, lang key]. Hand-maintained
 * (unlike terr()'s auto crc32 codes, see handoff.md 4.23) so every code the
 * app can show is browsable in one place. `type` is 'error' / 'warning' /
 * 'success' — mapped to Bootstrap's text-bg-{type} in app.js's
 * dsNotifyCode() ('error' → 'danger', the other two match Bootstrap's own
 * naming).
 *
 * Adding one: pick a free 4-digit code, add an entry below, call
 * `dsNotifyCode(code)` from the client-side JS that detects the condition —
 * this catalog only ever fires toasts, never a server-rendered page. If the
 * same rule also has a terr()-driven inline validation error (an
 * $errors[...] on some form), reuse that exact number here so the toast and
 * the inline message agree instead of showing two different codes for the
 * same thing.
 */
return [
    4418 => ['type' => 'error', 'key' => 'prod.err_image_size'],
    6817 => ['type' => 'error', 'key' => 'prod.err_image_type'],
];

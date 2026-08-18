<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Iban;
use App\Models\Organization;

/**
 * /settings/organization — the single-org's own info (see handoff.md):
 * name/tax id/contact/address/invoice prefix/bank details, plus a logo and
 * a signature image. Admin-only, same Auth::requireAdmin() gate as
 * /settings/users.
 */
final class OrganizationController extends Controller
{
    private const UPLOAD_DIR = ROOT_PATH . '/public/assets/uploads/organization/';
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    private const ALLOWED_EXT = ['jpg' => true, 'jpeg' => true, 'png' => true, 'webp' => true, 'svg' => true];

    public function show(): void
    {
        Auth::requireAdmin();

        $org   = Organization::get(Auth::tenantId());
        $old   = flash('old') ?? [];
        $ibans = $old['bank_ibans'] ?? Organization::bankIbans($org);
        if ($ibans === [] || end($ibans) !== '') {
            $ibans[] = '';
        }

        $this->view('organization', [
            'title'    => t('org.title') . ' · ' . app_name(),
            'org'      => $org,
            'errors'   => flash('errors') ?? [],
            'old'      => $old,
            'accounts' => array_map(static function (string $iban): array {
                $bank = Iban::bankInfo($iban);

                return [
                    'iban' => $iban,
                    'code' => $bank['code'] ?? Iban::extractCode($iban),
                    'name' => $bank['name'] ?? null,
                    'logo' => $bank['logo'] ?? null,
                ];
            }, $ibans),
            'banks'    => Iban::knownBanks(),
            'updated'  => flash('updated'),
        ]);
    }

    public function save(): void
    {
        Auth::requireAdmin();
        csrf_verify();
        Auth::requireNotImpersonating();

        $ruler = Auth::tenantId();

        [$clean, $errors] = Organization::validate($_POST);

        $logo      = $this->resolveImage('logo', $ruler, $errors);
        $signature = $this->resolveImage('signature', $ruler, $errors);

        if ($errors) {
            flash('errors', $errors);
            flash('old', $clean);
            redirect('/settings/organization');
        }

        Organization::save($clean, $ruler);
        if ($logo !== null) {
            Organization::updateLogo($logo, $ruler);
        }
        if ($signature !== null) {
            Organization::updateSignature($signature, $ruler);
        }

        flash('updated', true);
        redirect('/settings/organization');
    }

    /** Same shape as Warehouse/Profile/User's own upload handling — one of two independent image fields. */
    private function resolveImage(string $field, int $ruler, array &$errors): ?string
    {
        $file = $_FILES[$field] ?? null;
        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[$field] = terr('prod.err_image');
            return null;
        }

        if ($file['size'] > self::MAX_IMAGE_BYTES) {
            $errors[$field] = terr('prod.err_image_size');
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_EXT[$ext])) {
            $errors[$field] = terr('org.err_image_type');
            return null;
        }

        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }

        $existing = (string) (Organization::get($ruler)[$field] ?? '');
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], self::UPLOAD_DIR . $filename);

        if ($existing !== '' && is_file(self::UPLOAD_DIR . $existing)) {
            unlink(self::UPLOAD_DIR . $existing);
        }

        return $filename;
    }
}

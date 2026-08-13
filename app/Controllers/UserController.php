<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;

/**
 * /settings/users — the admin adding/editing sub-users (name/email/phone/
 * avatar/password/role). Admin-only: Auth::requireAdmin() bounces anyone
 * else to /. Same add-or-edit-in-one-form pattern as customers.php: a
 * hidden id decides whether store() creates or updates.
 */
final class UserController extends Controller
{
    private const UPLOAD_DIR = ROOT_PATH . '/public/assets/uploads/avatars/';
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    private const ALLOWED_EXT = ['jpg' => true, 'jpeg' => true, 'png' => true, 'webp' => true];

    public function index(): void
    {
        Auth::requireAdmin();
        $rows = User::all();

        $this->view('users', [
            'title'   => t('users.title') . ' · ' . app_name(),
            'rows'    => $rows,
            'roles'   => User::roles(),
            'total'   => count($rows),
            'errors'  => flash('errors') ?? [],
            'old'     => flash('old') ?? [],
            'created' => flash('created'),
            'updated' => flash('updated'),
        ]);
    }

    public function store(): void
    {
        Auth::requireAdmin();
        csrf_verify();

        $id        = trim((string) ($_POST['user_id'] ?? ''));
        $editingId = ctype_digit($id) ? (int) $id : null;

        [$clean, $errors] = User::validateSubUser($_POST, $editingId);

        $existingAvatar = $editingId !== null ? (string) (User::findById($editingId)['avatar'] ?? '') : '';
        $newAvatar      = $this->validateAvatar($errors);

        if ($errors) {
            flash('errors', $errors);
            flash('old', $clean + ['user_id' => $id]);
            redirect('/settings/users#user-form');
        }

        $clean['avatar'] = $newAvatar !== null ? $this->storeAvatar($newAvatar, $existingAvatar) : $existingAvatar;

        if ($editingId !== null) {
            User::updateSubUser($editingId, $clean);
            flash('updated', $clean['name']);
        } else {
            User::createSubUser($clean, (int) Auth::user()['id']);
            flash('created', $clean['name']);
        }

        redirect('/settings/users');
    }

    /**
     * Checks the uploaded file's constraints without touching disk — name/email/
     * phone/role/password can still reject the submission, and moving the file
     * before that check was leaving orphaned uploads in UPLOAD_DIR with nothing
     * in the DB pointing at them whenever some other field failed validation.
     *
     * @return array{tmp_name:string,ext:string}|null null if there's no new file to save
     */
    private function validateAvatar(array &$errors): ?array
    {
        $file = $_FILES['avatar'] ?? null;
        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['avatar'] = terr('prod.err_image');
            return null;
        }

        if ($file['size'] > self::MAX_IMAGE_BYTES) {
            $errors['avatar'] = terr('prod.err_image_size');
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_EXT[$ext])) {
            $errors['avatar'] = terr('prod.err_image_type');
            return null;
        }

        return ['tmp_name' => $file['tmp_name'], 'ext' => $ext];
    }

    /** Only called once the whole submission is known-valid. */
    private function storeAvatar(array $file, string $existing): string
    {
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $file['ext'];
        move_uploaded_file($file['tmp_name'], self::UPLOAD_DIR . $filename);

        if ($existing !== '' && is_file(self::UPLOAD_DIR . $existing)) {
            unlink(self::UPLOAD_DIR . $existing);
        }

        return $filename;
    }
}

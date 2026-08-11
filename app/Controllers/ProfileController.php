<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;

/**
 * The logged-in user's own profile + account settings — the "პროფილი"/
 * "პარამეტრები" links in the topbar user dropdown, previously href="#".
 * There's still no app-wide auth gate (see handoff.md 4.20), but a page
 * about "your account" has nothing to show an anonymous visitor, so it
 * gates itself: no session → straight to /login (Auth::requireUser()).
 */
final class ProfileController extends Controller
{
    private const UPLOAD_DIR = ROOT_PATH . '/public/assets/uploads/avatars/';
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    private const ALLOWED_EXT = ['jpg' => true, 'jpeg' => true, 'png' => true, 'webp' => true];

    public function show(): void
    {
        $user = Auth::requireUser();

        $this->view('profile', [
            'title'   => t('profile.title') . ' · ' . app_name(),
            'user'    => $user,
            'errors'  => flash('errors') ?? [],
            'old'     => flash('old') ?? [],
            'updated' => flash('updated'),
        ]);
    }

    public function update(): void
    {
        $user = Auth::requireUser();
        csrf_verify();

        [$clean, $errors] = User::validateProfileUpdate($_POST, (int) $user['id']);

        $avatar = $this->resolveAvatar($errors, (string) ($user['avatar'] ?? ''));
        $clean['avatar'] = $avatar ?? (string) ($user['avatar'] ?? '');

        if ($errors) {
            flash('errors', $errors);
            flash('old', $clean);
            redirect('/profile');
        }

        User::updateProfile((int) $user['id'], $clean);
        flash('updated', true);
        redirect('/profile');
    }

    public function settings(): void
    {
        $user = Auth::requireUser();

        $this->view('profile-settings', [
            'title'   => t('profile.settings.title') . ' · ' . app_name(),
            'user'    => $user,
            'errors'  => flash('errors') ?? [],
            'updated' => flash('updated'),
        ]);
    }

    public function updatePassword(): void
    {
        $user = Auth::requireUser();
        csrf_verify();

        $current  = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');
        $errors   = [];

        // No existing password (Google-only account) — nothing to verify, this sets the first one.
        if ($user['password_hash'] !== null && !password_verify($current, $user['password_hash'])) {
            $errors['current_password'] = terr('profile.err_current_password');
        }

        if (strlen($password) < 8) {
            $errors['password'] = terr('auth.err_password_min');
        } elseif ($password !== $confirm) {
            $errors['password_confirm'] = terr('auth.err_password_mismatch');
        }

        if ($errors) {
            flash('errors', $errors);
            redirect('/profile/settings');
        }

        User::updatePassword((int) $user['id'], $password);
        flash('updated', true);
        redirect('/profile/settings');
    }

    /**
     * Same shape as Warehouse's own upload handling. Returns null when the
     * form didn't include a new file (caller should keep the existing
     * avatar) — distinct from "" which would clear it.
     */
    private function resolveAvatar(array &$errors, string $existing): ?string
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

        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], self::UPLOAD_DIR . $filename);

        if ($existing !== '' && is_file(self::UPLOAD_DIR . $existing)) {
            unlink(self::UPLOAD_DIR . $existing);
        }

        return $filename;
    }
}

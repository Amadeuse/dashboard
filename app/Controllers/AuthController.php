<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Db;
use App\Core\GoogleOAuth;
use App\Core\Mailer;
use App\Core\Sms;
use App\Core\Pixabay;
use App\Models\User;

/**
 * login/register/forgot-password/reset-password — no auth gate exists yet
 * (deliberate, see handoff.md), so these pages only establish a session;
 * they don't restrict anything else in the app.
 */
final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/');
        }

        $this->bare('auth/login', [
            'title'      => t('auth.login.title') . ' · ' . app_name(),
            'errors'     => flash('errors') ?? [],
            'old'        => flash('old') ?? [],
            'notice'     => flash('notice'),
        ]);
    }

    public function login(): void
    {
        csrf_verify();
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!Auth::attempt($email, $password, isset($_POST['remember']))) {
            flash('errors', ['password' => terr('auth.err_invalid_credentials')]);
            flash('old', ['email' => $email]);
            redirect('/login');
        }

        redirect('/');
    }

    /** AJAX: generates a 6-digit code and sends it by email or SMS, kept in the session (not DB — short-lived, single use). */
    public function sendOtp(): void
    {
        csrf_verify();
        header('Content-Type: application/json');

        $channel  = ($_POST['channel'] ?? '') === 'sms' ? 'sms' : 'email';
        $identity = trim((string) ($_POST['identity'] ?? ''));
        $user     = $channel === 'sms' ? User::findByPhone(Sms::normalize($identity) ?? $identity) : User::findByEmail($identity);

        if ($user === null) {
            echo json_encode(['ok' => false, 'error' => terr('auth.err_no_account')]);
            return;
        }

        $code = (string) random_int(100000, 999999);
        $_SESSION['otp'] = [
            'channel'  => $channel,
            'identity' => $channel === 'sms' ? $user['phone'] : $identity,
            'hash'     => password_hash($code, PASSWORD_DEFAULT),
            'expires'  => time() + 600,
        ];

        $sent = $channel === 'sms'
            ? Sms::send($user['phone'], t('auth.otp.sms_text', $code))
            : Mailer::send($identity, t('auth.otp.mail_subject'), t('auth.otp.mail_body', $code));

        echo json_encode($sent ? ['ok' => true] : ['ok' => false, 'error' => terr('auth.err_mail_failed')]);
    }

    public function verifyOtp(): void
    {
        csrf_verify();
        $channel  = ($_POST['channel'] ?? '') === 'sms' ? 'sms' : 'email';
        $identity = trim((string) ($_POST['identity'] ?? ''));
        $code     = trim((string) ($_POST['code'] ?? ''));
        $otp      = $_SESSION['otp'] ?? null;
        $matchIdentity = $channel === 'sms' ? (Sms::normalize($identity) ?? $identity) : $identity;

        if ($otp === null || $otp['channel'] !== $channel || $otp['identity'] !== $matchIdentity
            || $otp['expires'] < time() || !password_verify($code, $otp['hash'])
        ) {
            flash('errors', ['otp' => terr('auth.err_invalid_code')]);
            flash('old', ['tab' => 'otp']);
            redirect('/login');
        }

        unset($_SESSION['otp']);
        $user = $channel === 'sms' ? User::findByPhone($matchIdentity) : User::findByEmail($identity);
        Auth::login((int) $user['id']);
        redirect('/');
    }

    public function logout(): void
    {
        csrf_verify();
        Auth::logout();
        redirect('/login');
    }

    /** AJAX: one photo for the auth pages' rotating visual panel. {url:null} if Pixabay isn't configured. */
    public function authPhoto(): void
    {
        header('Content-Type: application/json');
        echo json_encode(Pixabay::randomPhoto('finance') ?? ['url' => null]);
    }

    public function showRegister(): void
    {
        if (Auth::check()) {
            redirect('/');
        }

        $this->bare('auth/register', [
            'title'      => t('auth.register.title') . ' · ' . app_name(),
            'errors'     => flash('errors') ?? [],
            'old'        => flash('old') ?? [],
        ]);
    }

    public function register(): void
    {
        csrf_verify();
        [$clean, $errors] = User::validateRegistration($_POST);
        if ($errors) {
            flash('errors', $errors);
            flash('old', $clean);
            redirect('/register');
        }

        $id = User::create($clean['name'], $clean['email'], $clean['password'], $clean['phone']);
        Auth::login($id);
        redirect('/');
    }

    /** "Continue with Google" — start of the OAuth2 authorization-code flow. */
    public function googleRedirect(): void
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;
        redirect(GoogleOAuth::authorizeUrl($state));
    }

    public function googleCallback(): void
    {
        $state = (string) ($_GET['state'] ?? '');
        $expected = (string) ($_SESSION['google_oauth_state'] ?? '');
        unset($_SESSION['google_oauth_state']);

        if ($expected === '' || !hash_equals($expected, $state)) {
            flash('notice', terr('auth.err_oauth_failed'));
            redirect('/login');
        }

        $code    = (string) ($_GET['code'] ?? '');
        $profile = $code !== '' ? GoogleOAuth::exchangeCode($code) : null;
        if ($profile === null) {
            flash('notice', terr('auth.err_oauth_failed'));
            redirect(Auth::check() ? '/profile/settings' : '/login');
        }

        // Already signed in → this is "Connect Google" from the account settings
        // page, not a login attempt. Link to the current session's account
        // instead of the normal find-or-create-by-email flow below.
        if (Auth::check()) {
            $current = Auth::user();
            $owner   = User::findByGoogleId($profile['sub']);
            if ($owner !== null && (int) $owner['id'] !== (int) $current['id']) {
                flash('notice', terr('profile.settings.err_google_taken'));
                redirect('/profile/settings');
            }

            User::linkGoogleId((int) $current['id'], $profile['sub']);
            flash('updated', true);
            redirect('/profile/settings');
        }

        $user = User::findByGoogleId($profile['sub']);
        if ($user === null) {
            $existing = User::findByEmail($profile['email']);
            if ($existing !== null) {
                User::linkGoogleId((int) $existing['id'], $profile['sub']);
                $user = $existing;
            } else {
                $user = ['id' => User::createFromGoogle($profile['name'], $profile['email'], $profile['sub'])];
            }
        }

        Auth::login((int) $user['id']);
        redirect('/');
    }

    public function showForgot(): void
    {
        $this->bare('auth/forgot-password', [
            'title'      => t('auth.forgot.title') . ' · ' . app_name(),
            'errors'     => flash('errors') ?? [],
            'old'        => flash('old') ?? [],
            'sent'       => flash('sent'),
        ]);
    }

    public function sendResetLink(): void
    {
        csrf_verify();
        $email = trim((string) ($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('errors', ['email' => terr('auth.err_email_invalid')]);
            flash('old', ['email' => $email]);
            redirect('/forgot-password');
        }

        // Same "sent" flash whether or not the address is registered — don't
        // let this form be used to probe which emails have accounts.
        if (User::emailExists($email)) {
            $token = bin2hex(random_bytes(32));
            Db::conn()->prepare(
                'INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at)'
            )->execute([$email, hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);

            $scheme = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
            $link   = "$scheme://{$_SERVER['HTTP_HOST']}/reset-password?email=" . rawurlencode($email) . "&token=$token";

            Mailer::send($email, t('auth.reset.mail_subject'), t('auth.reset.mail_body', $link));
        }

        flash('sent', true);
        redirect('/forgot-password');
    }

    public function showReset(): void
    {
        $email = (string) ($_GET['email'] ?? '');
        $token = (string) ($_GET['token'] ?? '');

        if (!$this->validResetToken($email, $token)) {
            flash('notice', terr('auth.err_reset_link_invalid'));
            redirect('/forgot-password');
        }

        $this->bare('auth/reset-password', [
            'title'      => t('auth.reset.title') . ' · ' . app_name(),
            'email'      => $email,
            'token'      => $token,
            'errors'     => flash('errors') ?? [],
        ]);
    }

    public function resetPassword(): void
    {
        csrf_verify();
        $email    = (string) ($_POST['email'] ?? '');
        $token    = (string) ($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        if (!$this->validResetToken($email, $token)) {
            flash('notice', terr('auth.err_reset_link_invalid'));
            redirect('/forgot-password');
        }

        $errors = [];
        if (strlen($password) < 8) {
            $errors['password'] = terr('auth.err_password_min');
        } elseif ($password !== $confirm) {
            $errors['password_confirm'] = terr('auth.err_password_mismatch');
        }

        if ($errors) {
            flash('errors', $errors);
            redirect('/reset-password?email=' . rawurlencode($email) . "&token=$token");
        }

        $user = User::findByEmail($email);
        User::updatePassword((int) $user['id'], $password);
        Db::conn()->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);

        flash('notice', t('auth.reset.done'));
        redirect('/login');
    }

    private function validResetToken(string $email, string $token): bool
    {
        if ($email === '' || $token === '') {
            return false;
        }

        $row = Db::all('SELECT * FROM password_resets WHERE email = ?', [$email])[0] ?? null;

        return $row !== null
            && hash_equals($row['token_hash'], hash('sha256', $token))
            && strtotime($row['expires_at']) > time();
    }
}

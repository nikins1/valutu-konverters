<?php
declare(strict_types=1);

namespace App\Security;

use App\Config;
use App\Repository\UserRepository;
use App\View;

/** Autorizācija: sesijas, pieteikšanās, lomu pārbaude. */
final class Auth
{
    private static ?string $dummyHash = null;

    /** Drošas sesijas sīkdatnes iestatījumi (httponly – JavaScript nevar nolasīt, samesite – papildu CSRF aizsardzība). */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_name('CCSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $https,
        ]);
        ini_set('session.use_strict_mode', '1'); // serveris nepieņem svešus sesijas ID
        session_start();
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    /** Ja lietotājs nav pieteicies, pārsūta uz pieteikšanās lapu. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            View::flash('info', 'Lai turpinātu, lūdzu, pieteikties.');
            View::redirect('index.php?page=login');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            echo 'Šī darbība pieejama tikai administratoram.';
            exit;
        }
    }

    /**
     * Mēģina pieteikt lietotāju. Atgriež kļūdas tekstu vai null, ja viss kārtībā.
     * Kļūdas teksts ir vienāds gan nepareizam lietotājvārdam, gan parolei (neatklājam, kurš no tiem bija nepareizs).
     */
    public static function login(UserRepository $users, string $username, string $password): ?string
    {
        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $max     = (int) Config::get('login_max_attempts');
        $minutes = (int) Config::get('login_window_minutes');

        if ($users->recentFailures($username, $ip, $minutes) >= $max) {
            return "Pārāk daudz neveiksmīgu mēģinājumu. Mēģiniet vēlreiz pēc {$minutes} minūtēm.";
        }

        $user = $users->findByUsername($username);

        // Arī neesoša lietotāja gadījumā tiek veikta paroles pārbaude, lai atbildes laiks neatklātu, vai lietotājs eksistē.
        $hash = $user['password_hash'] ?? self::dummyHash();
        $ok   = password_verify($password, $hash) && $user !== null;

        if (!$ok) {
            $users->logFailure($username, $ip);
            return 'Nepareizs lietotājvārds vai parole.';
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        $users->clearFailures($username, $ip);
        session_regenerate_id(true); // jauns sesijas ID pēc pieteikšanās (aizsardzība pret session fixation)
        $_SESSION['user'] = [
            'id'       => (int) $user['id'],
            'username' => $user['username'],
            'role'     => $user['role'],
        ];
        return null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= password_hash('neesoss-lietotajs', PASSWORD_DEFAULT);
    }
}

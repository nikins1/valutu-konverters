<?php
declare(strict_types=1);

namespace App\Security;

/**
 * Aizsardzība pret CSRF uzbrukumiem (OWASP CSRF Prevention Cheat Sheet – "synchronizer token").
 * Katrai sesijai tiek ģenerēts nejaušs marķieris, un katra POST forma to nosūta līdzi.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /** HTML slēptais lauks formām. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    /** Salīdzina marķierus ar hash_equals (aizsardzība pret laika uzbrukumiem). */
    public static function verify(mixed $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $token);
    }

    /** Pārtrauc pieprasījumu, ja POST datos nav derīga marķiera. */
    public static function requireValid(): void
    {
        if (!self::verify($_POST['csrf'] ?? null)) {
            http_response_code(403);
            echo 'Nederīgs drošības marķieris (CSRF). Atgriezieties atpakaļ, atsvaidziniet lapu un mēģiniet vēlreiz.';
            exit;
        }
    }
}

<?php
declare(strict_types=1);

use App\Security\Auth;

// Automātiskā klašu ielāde: klase App\Foo\Bar tiek ielādēta no src/Foo/Bar.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

error_reporting(E_ALL);
ini_set('display_errors', '0'); // tehniskas kļūdas lietotājam nerādām (OWASP: kļūdu apstrāde)
ini_set('log_errors', '1');
date_default_timezone_set('Europe/Riga');
mb_internal_encoding('UTF-8');

/** Īsais nosaukums izvades aizsardzībai pret XSS (lieto skatos). */
function e(mixed $value): string
{
    return \App\View::e($value);
}

if (PHP_SAPI !== 'cli') {
    // Drošības galvenes (OWASP Secure Headers): atļauti tikai paša servera skripti un stili
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; frame-ancestors 'none'; form-action 'self'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    Auth::startSession();
}

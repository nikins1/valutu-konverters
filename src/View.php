<?php
declare(strict_types=1);

namespace App;

/** Skatu (HTML veidņu) attēlošana, izvades aizsardzība un īslaicīgie paziņojumi. */
final class View
{
    /** Attēlo skatu views/<template>.php iekšā kopējā izkārtojumā (layout.php). */
    public static function render(string $template, array $vars = []): void
    {
        $root = Config::get('root') . '/views/';
        extract($vars, EXTR_SKIP);
        ob_start();
        require $root . $template . '.php';
        $content = ob_get_clean();
        require $root . 'layout.php';
    }

    /** HTML izvades aizsardzība pret XSS (OWASP). Katrs lietotāja ievadīts teksts iet cauri šai funkcijai. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Skaitļa formatēšana latviešu stilā (komats, atstarpe tūkstošiem). */
    public static function num(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals, ',', ' ');
    }

    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    /** Saglabā paziņojumu, kas tiks parādīts nākamajā lapā. */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /** Izņem un notīra visus saglabātos paziņojumus. */
    public static function takeFlashes(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }
}

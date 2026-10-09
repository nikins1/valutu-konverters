<?php
declare(strict_types=1);

/**
 * Vienīgais ieejas punkts (front controller). Maršrutēšana notiek pēc ?page= parametra,
 * un atļauti ir tikai tabulā norādītie maršruti.
 */

require __DIR__ . '/../src/bootstrap.php';

use App\Controller\AuthController;
use App\Controller\ConverterController;
use App\Controller\RatesController;
use App\Database;
use App\Repository\ConversionRepository;
use App\Repository\RateRepository;
use App\Repository\UserRepository;
use App\Security\Auth;
use App\Service\ConverterService;
use App\Service\FrankfurterClient;
use App\Service\RateSyncService;
use App\View;

try {
    $pdo       = Database::pdo();
    $users     = new UserRepository($pdo);
    $rates     = new RateRepository($pdo);
    $history   = new ConversionRepository($pdo);
    $converter = new ConverterService($rates);

    $auth      = new AuthController($users);
    $convCtrl  = new ConverterController($rates, $history, $converter);
    $ratesCtrl = new RatesController($rates, $converter, new RateSyncService(new FrankfurterClient(), $rates, $pdo));

    // Maršrutu tabula: "METODE:lapa" => [kontrolieris, darbība]
    $routes = [
        'GET:login'      => [$auth, 'showLogin'],
        'POST:login'     => [$auth, 'login'],
        'GET:register'   => [$auth, 'showRegister'],
        'POST:register'  => [$auth, 'register'],
        'POST:logout'    => [$auth, 'logout'],
        'GET:converter'  => [$convCtrl, 'show'],
        'POST:converter' => [$convCtrl, 'convert'],
        'POST:clear'     => [$convCtrl, 'clearHistory'],
        'GET:rates'      => [$ratesCtrl, 'show'],
        'POST:sync'      => [$ratesCtrl, 'sync'],
    ];

    $page   = is_string($_GET['page'] ?? null) ? $_GET['page'] : (Auth::check() ? 'converter' : 'login');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $key    = $method . ':' . $page;

    if (!isset($routes[$key])) {
        http_response_code(404);
        View::render('error', ['title' => 'Nav atrasts', 'message' => 'Šāda lapa neeksistē.']);
        exit;
    }
    [$controller, $action] = $routes[$key];
    $controller->$action();
} catch (Throwable $e) {
    // Tehnisko kļūdu ierakstām žurnālā, lietotājam rādām vispārīgu paziņojumu
    error_log($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    View::render('error', ['title' => 'Kļūda', 'message' => 'Radās neparedzēta kļūda. Mēģiniet vēlreiz vēlāk.']);
}

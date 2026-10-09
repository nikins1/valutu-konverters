<?php
declare(strict_types=1);

/**
 * Ielādē reālos ECB kursus no Frankfurter API datubāzē.
 *
 * Lietošana:
 *   php scripts/sync_rates.php          – pēdējās 90 dienas
 *   php scripts/sync_rates.php 365      – pēdējās 365 dienas (ne vairāk kā 3650)
 */

if (PHP_SAPI !== 'cli') {
    exit('Šo skriptu drīkst palaist tikai komandrindā.');
}
require __DIR__ . '/../src/bootstrap.php';

use App\Database;
use App\Repository\RateRepository;
use App\Service\FrankfurterClient;
use App\Service\RateSyncService;

$days = isset($argv[1]) && ctype_digit($argv[1]) ? min((int) $argv[1], 3650) : 90;

try {
    $pdo     = Database::pdo();
    $service = new RateSyncService(new FrankfurterClient(), new RateRepository($pdo), $pdo);
    $r       = $service->sync($days);
    echo "Gatavs: {$r['rates']} kursu ieraksti, {$r['currencies']} valūtas, periods {$r['from']} līdz {$r['to']}.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Kļūda: ' . $e->getMessage() . "\n");
    exit(1);
}

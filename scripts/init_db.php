<?php
declare(strict_types=1);

/**
 * Izveido tabulas MySQL datubāzē no database/schema.sql un database/seed.sql.
 * Datubāze (tukša) jāizveido iepriekš, piemēram, HeidiSQL, un tās dati jāieraksta src/Config.php.
 *
 * Lietošana:
 *   php scripts/init_db.php            – tabulas + administratora konts (parole tiek izdrukāta vienreiz)
 *   php scripts/init_db.php --demo     – papildus demonstrācijas kursi (simulēti, strādā bez interneta) un konts "demo"
 *   php scripts/init_db.php --force    – vispirms izdzēš esošās šīs programmas tabulas
 */

if (PHP_SAPI !== 'cli') {
    exit('Šo skriptu drīkst palaist tikai komandrindā.');
}
require __DIR__ . '/../src/bootstrap.php';

use App\Config;
use App\Database;
use App\Repository\RateRepository;
use App\Repository\UserRepository;

$opts  = array_slice($argv, 1);
$demo  = in_array('--demo', $opts, true);
$force = in_array('--force', $opts, true);

try {
    $pdo = Database::connect();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$tables = ['login_attempts', 'conversions', 'exchange_rates', 'currencies', 'users'];
$exists = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn() !== false;
if ($exists && !$force) {
    fwrite(STDERR, "Tabulas jau eksistē. Lai tās izdzēstu un izveidotu no jauna, pievienojiet --force.\n");
    exit(1);
}
if ($force) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `$t`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

/** Sadala SQL failu atsevišķos pieprasījumos (komentāru rindas tiek izlaistas). */
function sqlStatements(string $file): array
{
    $lines = array_filter(explode("\n", (string) file_get_contents($file)),
        static fn(string $l): bool => !str_starts_with(ltrim($l), '--'));
    return array_filter(array_map('trim', explode(";\n", implode("\n", $lines) . "\n")), static fn(string $s): bool => $s !== '');
}
foreach (['schema.sql', 'seed.sql'] as $f) {
    foreach (sqlStatements(Config::get('root') . '/database/' . $f) as $stmt) {
        $pdo->exec($stmt);
    }
}

$users = new UserRepository($pdo);
$rates = new RateRepository($pdo);

// Administrators ar nejaušu paroli (netiek glabāta kodā)
$adminPassword = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
$users->create('admin', null, password_hash($adminPassword, PASSWORD_DEFAULT), 'admin');
echo "Tabulas izveidotas datubāzē " . Config::get('db_name') . ".\n";
echo "Administrators: admin / $adminPassword   (saglabājiet paroli – tā vairs netiks rādīta)\n";

if ($demo) {
    $users->create('demo', null, password_hash('DemoParole2026', PASSWORD_DEFAULT));
    echo "Demonstrācijas lietotājs: demo / DemoParole2026\n";

    // Simulēti kursi: aptuvenas vērtības (cik valūtas uz 1 EUR) + gluda nejauša svārstība.
    // Fiksēta sēkla (mt_srand), lai kursi būtu vienādi katrā palaišanas reizē.
    $base = [
        'USD' => 1.13, 'GBP' => 0.85, 'CHF' => 0.93, 'JPY' => 172.0, 'SEK' => 11.0, 'NOK' => 11.6, 'DKK' => 7.46,
        'PLN' => 4.25, 'CZK' => 24.5, 'HUF' => 395.0, 'RON' => 5.08, 'BGN' => 1.9558, 'TRY' => 47.0,
        'CAD' => 1.60, 'AUD' => 1.78, 'CNY' => 8.3, 'BRL' => 6.3,
    ];
    mt_srand(2026);
    $days = 120;
    $pdo->beginTransaction();
    foreach ($base as $code => $start) {
        $value = $start;
        for ($i = $days; $i >= 0; $i--) {
            $date = new DateTimeImmutable("-{$i} days");
            if ((int) $date->format('N') >= 6) {
                continue; // brīvdienās kursu nav
            }
            if ($code === 'BGN') {
                $value = 1.95583; // BGN ir piesaistīta EUR
            } else {
                $value *= 1 + (mt_rand(-60, 60) / 10000); // dienas svārstība līdz ±0,6 %
            }
            $rates->upsertRate($date->format('Y-m-d'), $code, round($value, 6), 'demo');
        }
    }
    $pdo->commit();
    echo "Ielādēti demonstrācijas kursi par " . $days . " dienām (avots: demo).\n";
    echo "Reālos kursus var ielādēt ar: php scripts/sync_rates.php\n";
}

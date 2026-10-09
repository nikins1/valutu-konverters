<?php
declare(strict_types=1);

/**
 * Izveido vienu .sql failu (tabulas + dati), ko var ielādēt HeidiSQL: File > Run SQL file.
 * Fails: database/valutu_konverters.sql
 * Konvertāciju vēsture un pieteikšanās mēģinājumi netiek ieklauti.
 *
 * Lietošana: php scripts/export_sql.php
 */

if (PHP_SAPI !== 'cli') {
    exit('Šo skriptu drīkst palaist tikai komandrindā.');
}
require __DIR__ . '/../src/bootstrap.php';

use App\Config;
use App\Database;

$pdo = Database::pdo();
$out = "-- Valūtu konvertētājs: datubāze ar demonstrācijas datiem (MySQL)\n"
     . "-- Ielāde: HeidiSQL > izvēlieties savu jauno (tukšo) datubāzi > File > Run SQL file\n\n";
$out .= trim((string) file_get_contents(Config::get('root') . '/database/schema.sql')) . "\n\n";
$out .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

/** Ieraksta INSERT pieprasījumus partijās pa 200 rindām. */
function dumpTable(PDO $pdo, string $table, array $columns, string $order): string
{
    $rows = $pdo->query('SELECT `' . implode('`,`', $columns) . "` FROM `$table` ORDER BY $order")->fetchAll(PDO::FETCH_NUM);
    if (!$rows) {
        return "-- tabula $table ir tukša\n\n";
    }
    $sql = "-- Dati: $table\n";
    foreach (array_chunk($rows, 200) as $chunk) {
        $values = array_map(static function (array $r) use ($pdo): string {
            return '(' . implode(', ', array_map(static fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)) . ')';
        }, $chunk);
        $sql .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n" . implode(",\n", $values) . ";\n";
    }
    return $sql . "\n";
}

$out .= dumpTable($pdo, 'users', ['id', 'username', 'email', 'password_hash', 'role', 'created_at'], 'id');
$out .= dumpTable($pdo, 'currencies', ['code', 'name'], 'code');
$out .= dumpTable($pdo, 'exchange_rates', ['rate_date', 'currency_code', 'rate', 'source'], 'rate_date, currency_code');
$out .= "SET FOREIGN_KEY_CHECKS = 1;\n";

$file = Config::get('root') . '/database/valutu_konverters.sql';
file_put_contents($file, $out);
echo "Izveidots: $file (" . round(strlen($out) / 1024) . " KB)\n";

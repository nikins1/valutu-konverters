<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use RuntimeException;

/** Datubāzes savienojums (MySQL, izmantojot PDO). Vienā pieprasījumā tiek izveidots tikai viens savienojums. */
final class Database
{
    private static ?PDO $pdo = null;

    /** Atver savienojumu ar iestatījumiem no Config. Ja $withDb ir false, savienojas bez datubāzes izvēles. */
    public static function connect(bool $withDb = true): PDO
    {
        $dsn = 'mysql:host=' . Config::get('db_host') . ';port=' . Config::get('db_port')
             . ($withDb ? ';dbname=' . Config::get('db_name') : '') . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, (string) Config::get('db_user'), (string) Config::get('db_pass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // kļūdas kā izņēmumi
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                    // īstie sagatavotie pieprasījumi
            ]);
        } catch (PDOException $e) {
            // Paroli un citus datus kļūdas tekstā nerādām tālāk
            throw new RuntimeException('Neizdevās pieslēgties datubāzei (pārbaudiet iestatījumus src/Config.php): ' . $e->getMessage(), 0, $e);
        }
        return $pdo;
    }

    public static function pdo(): PDO
    {
        return self::$pdo ??= self::connect();
    }
}

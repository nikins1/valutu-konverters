<?php
declare(strict_types=1);

namespace App;

/**
 * Vienots konfigurācijas punkts – visi iestatījumi glabājas šeit,
 * lai tos nevajadzētu meklēt pa visu kodu.
 */
final class Config
{
    private static ?array $values = null;

    public static function get(string $key): mixed
    {
        if (self::$values === null) {
            self::$values = [
                'root'                 => dirname(__DIR__),

                // ===== MySQL savienojums =====
                'db_host'              => '153.92.1.116',
                'db_port'              => '3308',
                'db_name'              => 'bn_valuta',
                'db_user'              => 'borodins_n',
                'db_pass'              => '',
                // =============================

                'api_base'             => 'https://api.frankfurter.dev/v1', // atvērtais kursu API (ECB dati)
                'base_currency'        => 'EUR',        // visi kursi tiek glabāti attiecībā pret EUR
                'first_rate_date'      => '1999-01-04', // ECB kursu sērija sākas ar šo datumu
                'max_range_days'       => 366,          // maksimālais vēstures periods vienā pieprasījumā
                'max_amount'           => 1000000000,   // tehniskais ievades slieksnis (aizsardzība pret absurdām vērtībām)
                'cash_limit_eur'       => 7200,         // skaidras naudas darījuma limits EUR (likums "Par nodokļiem un nodevām", 30. pants)
                'login_max_attempts'   => 5,            // neveiksmīgo pieteikšanās mēģinājumu limits
                'login_window_minutes' => 15,           // laika logs limita skaitīšanai
            ];
        }
        return self::$values[$key];
    }
}
<?php
declare(strict_types=1);

namespace App\Validation;

use App\Config;
use DateTimeImmutable;

/**
 * Datumu pārbaudes noteikumi.
 *
 * ŠIS FAILS IR PĀRVEIDOTS NO GATAVA KODA.
 * Avots:   GitHub projekts cmbuckley/exchange-rates, fails src/Classes/Validation.php
 *          https://github.com/cmbuckley/exchange-rates  (komits f6062077, 2024-03-12)
 * Licence: MIT. Autortiesības (c) 2024 Chris Buckley, pirmajai versijai (c) 2018 Ashley Allen.
 *          Licences teksts ir failā docs/licenses/exchange-rates-LICENSE.md
 *
 * Kas oriģinālā bija:  datums nedrīkst būt nākotnē, nedrīkst būt agrāks par 1999-01-04,
 *                      sākuma datumam jābūt pirms beigu datuma. Kļūdas tika mestas kā izņēmumi,
 *                      un datumi bija DateTime objekti.
 * Ko es pārveidoju:
 *  - metodes atgriež kļūdas tekstu latviski (vai null, ja viss kārtībā), nevis met izņēmumu,
 *    jo formā jārāda kļūda pie konkrētā lauka;
 *  - datumi tiek pieņemti kā teksts GGGG-MM-DD, un tiek pārbaudīts, vai tas ir reāls kalendāra
 *    datums (piemēram, 2026-02-31 tiek noraidīts);
 *  - agrākais datums nav ierakstīts kodā, bet ņemts no Config;
 *  - pievienota perioda maksimālā garuma pārbaude (366 dienas).
 */
final class DateRules
{
    /** Pārbauda vienu datumu. Atgriež kļūdas tekstu vai null. */
    public static function checkDate(string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errs = DateTimeImmutable::getLastErrors();

        if ($date === false
            || ($errs && ($errs['warning_count'] > 0 || $errs['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            return 'Ievadiet derīgu datumu formātā GGGG-MM-DD.';
        }

        $today    = new DateTimeImmutable('today');
        $earliest = new DateTimeImmutable((string) Config::get('first_rate_date'));

        if ($date > $today) {
            return 'Datums nedrīkst būt nākotnē.';
        }
        if ($date < $earliest) {
            return 'Datums nedrīkst būt agrāks par ' . $earliest->format('Y-m-d') . '.';
        }
        return null;
    }

    /** Pārbauda, vai sākums ir pirms beigām un periods nav pārāk garš. Datumiem jau jābūt derīgiem. */
    public static function checkRange(string $start, string $end): ?string
    {
        $from = new DateTimeImmutable($start);
        $to   = new DateTimeImmutable($end);

        if ($from > $to) {
            return 'Perioda sākumam jābūt pirms beigām.';
        }
        $maxDays = (int) Config::get('max_range_days');
        if ($from->diff($to)->days > $maxDays) {
            return 'Periods nedrīkst būt garāks par ' . $maxDays . ' dienām.';
        }
        return null;
    }
}

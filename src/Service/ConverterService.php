<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\RateRepository;

/** Konvertēšanas loģika un kursu vēstures aprēķini. Nesatur ne HTML, ne SQL. */
final class ConverterService
{
    public function __construct(private RateRepository $rates)
    {
    }

    /**
     * Konvertē summu. Kursi datubāzē ir "cik valūtas uz 1 EUR", tāpēc pāra kurss no A uz B = kurss(B) / kurss(A).
     * @return array{amount:float, amount_eur:float, from:string, to:string, rate:float, result:float, rate_date:string}|null
     */
    public function convert(string $from, string $to, float $amount): ?array
    {
        $date = $this->rates->latestDate();
        if ($date === null) {
            return null; // datubāzē vēl nav kursu
        }
        $fromRate = $this->rateFor($from, $date);
        $toRate   = $this->rateFor($to, $date);
        if ($fromRate === null || $toRate === null) {
            return null;
        }
        $rate = $toRate['rate'] / $fromRate['rate'];

        return [
            'amount'     => $amount,
            'amount_eur' => $amount / $fromRate['rate'], // summa EUR ekvivalentā (skaidras naudas limita pārbaudei)
            'from'       => $from,
            'to'         => $to,
            'rate'       => $rate,
            'result'     => round($amount * $rate, 2),
            'rate_date'  => min($fromRate['date'], $toRate['date']),
        ];
    }

    /**
     * Valūtu pāra kursa vēsture periodā.
     * @return array<string,float> datums => kurss (1 vienība $from = X vienības $to)
     */
    public function pairSeries(string $from, string $to, string $start, string $end): array
    {
        if ($from === $to) {
            return [];
        }
        $base  = 'EUR';
        $fromS = $from === $base ? null : $this->rates->series($from, $start, $end);
        $toS   = $to === $base ? null : $this->rates->series($to, $start, $end);

        if ($fromS !== null && $toS !== null) {
            $dates = array_intersect(array_keys($fromS), array_keys($toS)); // datumi, kuros ir abi kursi
        } else {
            $dates = array_keys($fromS ?? $toS);
        }

        $out = [];
        foreach ($dates as $d) {
            $out[$d] = ($toS[$d] ?? 1.0) / ($fromS[$d] ?? 1.0);
        }
        ksort($out);
        return $out;
    }

    /** Kopsavilkuma statistika par kursu rindu. */
    public function stats(array $series): ?array
    {
        if ($series === []) {
            return null;
        }
        $values = array_values($series);
        $first  = $values[0];
        $last   = $values[count($values) - 1];

        return [
            'min'    => min($values),
            'max'    => max($values),
            'avg'    => array_sum($values) / count($values),
            'first'  => $first,
            'last'   => $last,
            'change' => ($last - $first) / $first * 100, // izmaiņa procentos
            'count'  => count($values),
        ];
    }

    private function rateFor(string $code, string $date): ?array
    {
        if ($code === 'EUR') {
            return ['rate' => 1.0, 'date' => $date];
        }
        return $this->rates->rateOnOrBefore($code, $date);
    }
}

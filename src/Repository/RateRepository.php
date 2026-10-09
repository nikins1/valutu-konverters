<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Darbs ar tabulām currencies un exchange_rates (kursi tiek glabāti attiecībā pret EUR). */
final class RateRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,string> valūtas kods => nosaukums */
    public function currencies(): array
    {
        return $this->pdo->query('SELECT code, name FROM currencies ORDER BY code')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** @return string[] */
    public function currencyCodes(): array
    {
        return array_keys($this->currencies());
    }

    public function latestDate(): ?string
    {
        $date = $this->pdo->query('SELECT MAX(rate_date) FROM exchange_rates')->fetchColumn();
        return $date === false || $date === null ? null : (string) $date;
    }

    /** Pēdējā datuma kursu avots ('frankfurter' vai 'demo'). */
    public function latestSource(): ?string
    {
        $src = $this->pdo->query(
            'SELECT source FROM exchange_rates WHERE rate_date = (SELECT MAX(rate_date) FROM exchange_rates) LIMIT 1'
        )->fetchColumn();
        return $src === false ? null : (string) $src;
    }

    /** Pēdējais zināmais kurss uz norādīto datumu vai agrāk (brīvdienās kursa nav, tāpēc ņem iepriekšējo darba dienu). */
    public function rateOnOrBefore(string $code, string $date): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT rate, rate_date FROM exchange_rates
             WHERE currency_code = :c AND rate_date <= :d
             ORDER BY rate_date DESC LIMIT 1'
        );
        $st->execute([':c' => $code, ':d' => $date]);
        $row = $st->fetch();
        return $row === false ? null : ['rate' => (float) $row['rate'], 'date' => (string) $row['rate_date']];
    }

    /** @return array<string,float> datums => kurss (augošā datuma secībā) */
    public function series(string $code, string $from, string $to): array
    {
        $st = $this->pdo->prepare(
            'SELECT rate_date, rate FROM exchange_rates
             WHERE currency_code = :c AND rate_date BETWEEN :f AND :t
             ORDER BY rate_date'
        );
        $st->execute([':c' => $code, ':f' => $from, ':t' => $to]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[$row['rate_date']] = (float) $row['rate'];
        }
        return $out;
    }

    public function upsertCurrency(string $code, string $name): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO currencies (code, name) VALUES (:c, :n)
             ON DUPLICATE KEY UPDATE name = :n2'
        );
        $st->execute([':c' => $code, ':n' => $name, ':n2' => $name]);
    }

    public function upsertRate(string $date, string $code, float $rate, string $source): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO exchange_rates (rate_date, currency_code, rate, source) VALUES (:d, :c, :r, :s)
             ON DUPLICATE KEY UPDATE rate = :r2, source = :s2'
        );
        $st->execute([':d' => $date, ':c' => $code, ':r' => $rate, ':s' => $source, ':r2' => $rate, ':s2' => $source]);
    }

    public function deleteBySource(string $source): void
    {
        $st = $this->pdo->prepare('DELETE FROM exchange_rates WHERE source = :s');
        $st->execute([':s' => $source]);
    }
}

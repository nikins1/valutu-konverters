<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\RateRepository;
use DateTimeImmutable;
use PDO;

/** Ielādē kursus no API un saglabā tos datubāzē (vēsturi uzkrājam pie sevis, lai lapa strādātu ātri). */
final class RateSyncService
{
    /** API ļauj lūgt dienas kursus līdz ~90 dienām vienā pieprasījumā, tāpēc periodu dalām daļās. */
    private const CHUNK_DAYS = 89;

    public function __construct(
        private FrankfurterClient $api,
        private RateRepository $rates,
        private PDO $pdo,
    ) {
    }

    /**
     * Ielādē pēdējo $days dienu kursus.
     * @return array{currencies:int, rates:int, from:string, to:string}
     */
    public function sync(int $days): array
    {
        $end   = new DateTimeImmutable('today');
        $start = $end->modify('-' . max(1, $days) . ' days');

        // 1) Vispirms iegūstam visus datus (ja API nav pieejams, datubāze netiek mainīta)
        $names     = $this->api->currencies();
        $collected = [];
        for ($from = $start; $from <= $end; $from = $to->modify('+1 day')) {
            $to = min($from->modify('+' . self::CHUNK_DAYS . ' days'), $end);
            $data = $this->api->range($from->format('Y-m-d'), $to->format('Y-m-d'));
            foreach ($data['rates'] ?? [] as $date => $perCurrency) {
                $collected[$date] = $perCurrency;
            }
        }
        if ($collected === []) {
            throw new \RuntimeException('API neatgrieza nevienu kursu.');
        }

        // 2) Tad visu saglabājam vienā transakcijā
        $savedRates = 0;
        $seen       = [];
        $this->pdo->beginTransaction();
        try {
            $this->rates->deleteBySource('demo'); // demo dati tiek aizstāti ar īstajiem
            $this->rates->upsertCurrency('EUR', $names['EUR'] ?? 'Euro');
            foreach ($collected as $date => $perCurrency) {
                foreach ($perCurrency as $code => $rate) {
                    if (!is_string($code) || !preg_match('/^[A-Z]{3}$/', $code) || !is_numeric($rate) || $rate <= 0) {
                        continue; // nederīgas rindas izlaižam
                    }
                    if (!isset($seen[$code])) {
                        $this->rates->upsertCurrency($code, (string) ($names[$code] ?? $code));
                        $seen[$code] = true;
                    }
                    $this->rates->upsertRate((string) $date, $code, (float) $rate, 'frankfurter');
                    $savedRates++;
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'currencies' => count($seen) + 1,
            'rates'      => $savedRates,
            'from'       => $start->format('Y-m-d'),
            'to'         => $end->format('Y-m-d'),
        ];
    }
}

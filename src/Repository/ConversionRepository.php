<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Lietotāju konvertāciju vēsture (tabula conversions). */
final class ConversionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function add(int $userId, array $c): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO conversions (user_id, from_code, to_code, amount, result, rate_used, rate_date)
             VALUES (:u, :f, :t, :a, :r, :rate, :d)'
        );
        $st->execute([
            ':u' => $userId, ':f' => $c['from'], ':t' => $c['to'], ':a' => $c['amount'],
            ':r' => $c['result'], ':rate' => $c['rate'], ':d' => $c['rate_date'],
        ]);
    }

    public function recentByUser(int $userId, int $limit = 10): array
    {
        $st = $this->pdo->prepare(
            'SELECT from_code, to_code, amount, result, rate_used, rate_date, created_at
             FROM conversions WHERE user_id = :u ORDER BY id DESC LIMIT :l'
        );
        $st->bindValue(':u', $userId, PDO::PARAM_INT);
        $st->bindValue(':l', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    /** Lietotājs var dzēst savu vēsturi (GDPR 17. pants – tiesības uz dzēšanu). */
    public function clearForUser(int $userId): void
    {
        $st = $this->pdo->prepare('DELETE FROM conversions WHERE user_id = :u');
        $st->execute([':u' => $userId]);
    }
}

<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Darbs ar tabulām users un login_attempts. Visi pieprasījumi ir sagatavoti (aizsardzība pret SQL injekciju). */
final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByUsername(string $username): ?array
    {
        $st = $this->pdo->prepare('SELECT id, username, email, password_hash, role FROM users WHERE username = :u');
        $st->execute([':u' => $username]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public function usernameExists(string $username): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM users WHERE username = :u');
        $st->execute([':u' => $username]);
        return $st->fetchColumn() !== false;
    }

    public function emailExists(string $email): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :e');
        $st->execute([':e' => $email]);
        return $st->fetchColumn() !== false;
    }

    public function create(string $username, ?string $email, string $passwordHash, string $role = 'user'): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role) VALUES (:u, :e, :h, :r)'
        );
        $st->execute([':u' => $username, ':e' => $email, ':h' => $passwordHash, ':r' => $role]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $st = $this->pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
        $st->execute([':h' => $hash, ':id' => $id]);
    }

    /** Cik neveiksmīgu mēģinājumu bijis pēdējo N minūšu laikā. */
    public function recentFailures(string $username, string $ip, int $minutes): int
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE username = :u AND ip = :ip AND attempted_at >= (NOW() - INTERVAL :w MINUTE)'
        );
        $st->bindValue(':u', $this->shorten($username));
        $st->bindValue(':ip', $ip);
        $st->bindValue(':w', $minutes, PDO::PARAM_INT);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    public function logFailure(string $username, string $ip): void
    {
        $st = $this->pdo->prepare('INSERT INTO login_attempts (username, ip) VALUES (:u, :ip)');
        $st->execute([':u' => $this->shorten($username), ':ip' => $ip]);
    }

    public function clearFailures(string $username, string $ip): void
    {
        $st = $this->pdo->prepare('DELETE FROM login_attempts WHERE username = :u AND ip = :ip');
        $st->execute([':u' => $this->shorten($username), ':ip' => $ip]);
    }

    /** Pieteikšanās laukā var ierakstīt jebko, bet tabulā lauks ir 100 simbolus garš, tāpēc tekstu saīsinām. */
    private function shorten(string $username): string
    {
        return mb_substr($username, 0, 100);
    }
}

<?php
declare(strict_types=1);

namespace App\Validation;

use App\Config;

/**
 * Ievades datu validācija (servera pusē – tā ir obligātā; pārlūka pārbaude ir tikai ērtībai).
 * Katra metode atgriež sakopto vērtību vai null, ja ievade ir nederīga; kļūdas teksts tiek saglabāts pie lauka nosaukuma.
 * Izmantotie standarti un likumi ir aprakstīti Dokumentacija.docx.
 */
final class Validator
{
    /** Izplatītas vājas paroles (NIST SP 800-63B: jāpārbauda pret zināmu paroļu sarakstu). */
    private const COMMON_PASSWORDS = [
        'password', 'password1', 'password123', '12345678', '123456789', '1234567890', 'qwertyui', 'qwerty123',
        'iloveyou', 'admin123', 'letmein123', 'parole123', 'parole1234', '11111111', 'abcd1234', 'welcome123',
    ];

    private array $errors = [];

    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /** Pievieno kļūdu (tiek saglabāta tikai pirmā kļūda katram laukam). */
    public function fail(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    /** Lietotājvārds: 3–30 simboli, tikai latīņu burti, cipari un pasvītra. */
    public function username(string $field, mixed $raw): ?string
    {
        $value = is_string($raw) ? trim($raw) : '';
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $value)) {
            $this->fail($field, 'Lietotājvārdam jābūt 3–30 simbolus garam (latīņu burti, cipari, pasvītra).');
            return null;
        }
        return $value;
    }

    /** E-pasts nav obligāts. Formāts pēc RFC 5322 (filter_var), maksimālais garums 254 (RFC 5321). */
    public function email(string $field, mixed $raw): ?string
    {
        $value = is_string($raw) ? trim($raw) : '';
        if ($value === '') {
            return null;
        }
        if (strlen($value) > 254 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail($field, 'Ievadiet derīgu e-pasta adresi vai atstājiet lauku tukšu.');
            return null;
        }
        return $value;
    }

    /**
     * Parole pēc NIST SP 800-63B: garums 8–64, nav izplatīta parole, nav vienāda ar lietotājvārdu.
     * Sarežģītības noteikumi (obligāti lielie burti, simboli) netiek prasīti – NIST tos neiesaka.
     */
    public function password(string $field, mixed $raw, ?string $username = null): ?string
    {
        if (!is_string($raw)) {
            $this->fail($field, 'Ievadiet paroli.');
            return null;
        }
        $length = mb_strlen($raw);
        if ($length < 8 || $length > 64) {
            $this->fail($field, 'Parolei jābūt 8–64 simbolus garai.');
            return null;
        }
        if (in_array(mb_strtolower($raw), self::COMMON_PASSWORDS, true)
            || ($username !== null && mb_strtolower($raw) === mb_strtolower($username))) {
            $this->fail($field, 'Šī parole ir pārāk viegli uzminama. Izvēlieties citu.');
            return null;
        }
        return $raw;
    }

    /**
     * Summa: pozitīvs skaitlis ar ne vairāk kā 2 zīmēm aiz komata (komats vai punkts).
     * Pārbaude pret Latvijas skaidras naudas limitu (EUR ekvivalentā) notiek ConverterController, kad ir zināms kurss.
     */
    public function amount(string $field, mixed $raw): ?float
    {
        $value = is_string($raw) ? str_replace(',', '.', trim($raw)) : '';
        if (!preg_match('/^\d{1,12}(\.\d{1,2})?$/', $value)) {
            $this->fail($field, 'Ievadiet summu skaitļa formā, ne vairāk kā ar 2 zīmēm aiz komata.');
            return null;
        }
        $number = (float) $value;
        if ($number <= 0 || $number > (float) Config::get('max_amount')) {
            $this->fail($field, 'Summai jābūt lielākai par 0 un ne lielākai par ' . number_format((float) Config::get('max_amount'), 0, ',', ' ') . '.');
            return null;
        }
        return $number;
    }

    /** Valūtas kods: ISO 4217 formāts (3 lielie burti) un jābūt datubāzes atļauto kodu sarakstā. */
    public function currency(string $field, mixed $raw, array $allowedCodes): ?string
    {
        $value = is_string($raw) ? strtoupper(trim($raw)) : '';
        if (!preg_match('/^[A-Z]{3}$/', $value) || !in_array($value, $allowedCodes, true)) {
            $this->fail($field, 'Izvēlieties valūtu no saraksta.');
            return null;
        }
        return $value;
    }

    /**
     * Datums: ISO 8601 (GGGG-MM-DD), reāls kalendāra datums, ne agrāk par kursu sērijas sākumu un ne nākotnē.
     * Pati pārbaude ir klasē DateRules (pārveidots gatavs kods no GitHub, skatīt tās faila sākumu).
     */
    public function date(string $field, mixed $raw): ?string
    {
        $value = is_string($raw) ? trim($raw) : '';
        $error = DateRules::checkDate($value);
        if ($error !== null) {
            $this->fail($field, $error);
            return null;
        }
        return $value;
    }

    /** Pārbauda, vai perioda sākums nav pēc beigām un periods nav pārāk garš (DateRules::checkRange). */
    public function dateRange(string $field, ?string $start, ?string $end): void
    {
        if ($start === null || $end === null) {
            return;
        }
        $error = DateRules::checkRange($start, $end);
        if ($error !== null) {
            $this->fail($field, $error);
        }
    }
}

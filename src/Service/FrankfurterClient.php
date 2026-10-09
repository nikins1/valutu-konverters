<?php
declare(strict_types=1);

namespace App\Service;

use App\Config;
use App\Exceptions\ServiceException;

/**
 * Klients ārējam kursu API – Frankfurter (https://frankfurter.dev), kas publicē Eiropas Centrālās bankas kursus.
 * API atslēga nav vajadzīga. Izmantojam v1 galapunktus: /currencies un /{sākums}..{beigas}.
 *
 * ŠĪS KLASES PIEPRASĪJUMA VEIDOŠANAS DAĻA (makeRequest) IR PĀRVEIDOTA NO GATAVA KODA.
 * Avots:   GitHub projekts cmbuckley/exchange-rates, fails src/Classes/RequestBuilder.php
 *          https://github.com/cmbuckley/exchange-rates  (komits f6062077, 2024-03-12)
 * Licence: MIT, (c) 2024 Chris Buckley. Licences teksts: docs/licenses/exchange-rates-LICENSE.md
 *
 * Kas oriģinālā bija:  klase, kas ar Guzzle bibliotēku veido URL no ceļa un parametriem, izsauc
 *                      exchangerate.host API, atšifrē JSON un met ServiceException, ja atbildē
 *                      success ir false.
 * Ko es pārveidoju:
 *  - Guzzle neizmantoju (lai nav jāinstalē Composer pakotnes), tā vietā ir cURL ar rezerves
 *    variantu file_get_contents;
 *  - adrese un User-Agent ņemti no Config, API atslēgas nav;
 *  - Frankfurter atbildē nav lauka "success", tāpēc kļūda tiek noteikta pēc HTTP statusa koda;
 *  - kļūdu teksti latviski, un detalizēta informācija netiek rādīta lietotājam, bet nonāk žurnālā;
 *  - pievienota datumu formāta pārbaude pirms tie nonāk URL (metode range).
 */
final class FrankfurterClient
{
    /** @return array<string,string> valūtas kods => nosaukums */
    public function currencies(): array
    {
        return $this->makeRequest('currencies');
    }

    /** Kursi par periodu: ['rates' => ['2026-01-05' => ['USD' => 1.17, ...], ...]] */
    public function range(string $start, string $end): array
    {
        foreach ([$start, $end] as $d) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                throw new ServiceException('Nederīgs datums API pieprasījumā.');
            }
        }
        return $this->makeRequest($start . '..' . $end, ['base' => Config::get('base_currency')]);
    }

    /**
     * Veic pieprasījumu API un atgriež atšifrētu atbildi.
     * @throws ServiceException
     */
    private function makeRequest(string $path, array $queryParams = []): array
    {
        $url = rtrim((string) Config::get('api_base'), '/') . '/' . $path;
        if ($queryParams) {
            $url .= '?' . http_build_query($queryParams);
        }

        $body = function_exists('curl_init') ? $this->viaCurl($url) : $this->viaStream($url);

        try {
            $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ServiceException('API atbilde nav derīgs JSON.', 0, $e);
        }
        if (!is_array($response)) {
            throw new ServiceException('API atbilde nav derīgs JSON.');
        }
        return $response;
    }

    private function viaCurl(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'valutu-konverters/1.0 (skolas projekts)',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code !== 200) {
            throw new ServiceException("Neizdevās iegūt kursus no API (HTTP {$code}) {$err}");
        }
        return (string) $body;
    }

    private function viaStream(string $url): string
    {
        $ctx = stream_context_create(['http' => [
            'timeout'       => 20,
            'user_agent'    => 'valutu-konverters/1.0 (skolas projekts)',
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new ServiceException('Neizdevās izveidot savienojumu ar kursu API.');
        }
        return $body;
    }
}

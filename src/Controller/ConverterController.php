<?php
declare(strict_types=1);

namespace App\Controller;

use App\Config;
use App\Repository\ConversionRepository;
use App\Repository\RateRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Service\ConverterService;
use App\Validation\Validator;
use App\View;

/** Galvenā lapa: summas konvertēšana un lietotāja konvertāciju vēsture. */
final class ConverterController
{
    public function __construct(
        private RateRepository $rates,
        private ConversionRepository $conversions,
        private ConverterService $converter,
    ) {
    }

    public function show(): void
    {
        Auth::requireLogin();
        $this->render(new Validator(), ['amount' => '100', 'from' => 'EUR', 'to' => 'USD'], null);
    }

    public function convert(): void
    {
        Auth::requireLogin();
        Csrf::requireValid();

        $codes = $this->rates->currencyCodes();
        $v     = new Validator();

        $amount = $v->amount('amount', $_POST['amount'] ?? null);
        $from   = $v->currency('from', $_POST['from'] ?? null, $codes);
        $to     = $v->currency('to', $_POST['to'] ?? null, $codes);

        $result = null;
        if (!$v->hasErrors()) {
            $result = $this->converter->convert($from, $to, $amount);
            $limit  = (float) Config::get('cash_limit_eur');
            if ($result === null) {
                $v->fail('general', 'Šai valūtai nav pieejamu kursu. Administrators var tos atjaunot sadaļā "Kursu vēsture".');
            } elseif ($result['amount_eur'] > $limit) {
                // Summa nedrīkst pārsniegt Latvijas likumā noteikto skaidras naudas darījumu limitu (EUR ekvivalentā)
                $v->fail('amount', 'Summa nedrīkst pārsniegt ' . View::num($limit, 0)
                    . ' EUR ekvivalentu (skaidras naudas darījumu ierobežojums Latvijā). Jūsu summa ir aptuveni '
                    . View::num($result['amount_eur']) . ' EUR.');
                $result = null;
            } else {
                $this->conversions->add((int) Auth::user()['id'], $result);
            }
        }

        $old = [
            'amount' => is_string($_POST['amount'] ?? null) ? trim($_POST['amount']) : '',
            'from'   => $from ?? 'EUR',
            'to'     => $to ?? 'USD',
        ];
        if ($v->hasErrors()) {
            http_response_code(422);
        }
        $this->render($v, $old, $result);
    }

    public function clearHistory(): void
    {
        Auth::requireLogin();
        Csrf::requireValid();
        $this->conversions->clearForUser((int) Auth::user()['id']);
        View::flash('success', 'Konvertāciju vēsture izdzēsta.');
        View::redirect('index.php?page=converter');
    }

    private function render(Validator $v, array $old, ?array $result): void
    {
        View::render('converter', [
            'title'      => 'Konvertētājs',
            'v'          => $v,
            'old'        => $old,
            'result'     => $result,
            'currencies' => $this->rates->currencies(),
            'history'    => $this->conversions->recentByUser((int) Auth::user()['id']),
            'isDemo'     => $this->rates->latestSource() === 'demo',
            'latestDate' => $this->rates->latestDate(),
        ]);
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\RateRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Service\ConverterService;
use App\Service\RateSyncService;
use App\Validation\Validator;
use App\View;
use DateTimeImmutable;

/** Kursu vēstures lapa (tabula, diagramma, statistika) un kursu atjaunošana administratoram. */
final class RatesController
{
    public function __construct(
        private RateRepository $rates,
        private ConverterService $converter,
        private RateSyncService $sync,
    ) {
    }

    public function show(): void
    {
        Auth::requireLogin();

        $codes  = $this->rates->currencyCodes();
        $latest = $this->rates->latestDate() ?? date('Y-m-d');
        $v      = new Validator();

        $form = [
            'from'  => 'EUR',
            'to'    => 'USD',
            'start' => (new DateTimeImmutable($latest))->modify('-30 days')->format('Y-m-d'),
            'end'   => $latest,
        ];
        $series = [];

        // Ja forma ir iesniegta (GET parametri), validējam ievadi
        if (isset($_GET['from'], $_GET['to'], $_GET['start'], $_GET['end'])) {
            $from  = $v->currency('from', $_GET['from'], $codes);
            $to    = $v->currency('to', $_GET['to'], $codes);
            $start = $v->date('start', $_GET['start']);
            $end   = $v->date('end', $_GET['end']);
            $v->dateRange('start', $start, $end);
            if ($from !== null && $from === $to) {
                $v->fail('to', 'Izvēlieties divas dažādas valūtas.');
            }
            foreach (['from', 'to', 'start', 'end'] as $k) {
                $form[$k] = is_string($_GET[$k]) ? trim($_GET[$k]) : $form[$k];
            }
            if (!$v->hasErrors()) {
                $series = $this->converter->pairSeries($from, $to, $start, $end);
            }
        } else {
            $series = $this->converter->pairSeries($form['from'], $form['to'], $form['start'], $form['end']);
        }

        View::render('rates', [
            'title'      => 'Kursu vēsture',
            'v'          => $v,
            'form'       => $form,
            'currencies' => $this->rates->currencies(),
            'series'     => $series,
            'stats'      => $this->converter->stats($series),
            'isDemo'     => $this->rates->latestSource() === 'demo',
            'latestDate' => $this->rates->latestDate(),
            'withChart'  => $series !== [],
        ]);
    }

    /** Tikai administrators: ielādē jaunākos kursus no API. */
    public function sync(): void
    {
        Auth::requireAdmin();
        Csrf::requireValid();
        try {
            $r = $this->sync->sync(30);
            View::flash('success', "Kursi atjaunoti: {$r['rates']} ieraksti par periodu {$r['from']} – {$r['to']}.");
        } catch (\Throwable $e) {
            error_log('Kursu atjaunošana neizdevās: ' . $e->getMessage());
            View::flash('error', 'Neizdevās atjaunot kursus. Pārbaudiet interneta savienojumu un mēģiniet vēlreiz.');
        }
        View::redirect('index.php?page=rates');
    }
}

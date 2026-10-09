<?php
use App\Security\Auth;
use App\Security\Csrf;
use App\View;

$err = static function (string $field) use ($v): string {
    $m = $v->error($field);
    return $m === null ? '' : '<p class="field-error" id="' . $field . '-error" role="alert">' . e($m) . '</p>';
};
$options = static function (array $currencies, string $selected): string {
    $html = '';
    foreach ($currencies as $code => $name) {
        $sel = $code === $selected ? ' selected' : '';
        $html .= '<option value="' . e($code) . '"' . $sel . '>' . e($code) . ' – ' . e($name) . '</option>';
    }
    return $html;
};
$digits = static fn(float $r): int => $r < 0.01 ? 6 : 4;
?>
<h1>Kursu vēsture</h1>

<?php if ($isDemo): ?>
    <p class="notice warn">Pašlaik tiek rādīti demonstrācijas kursi, nevis reāli. Lai ielādētu īstos, palaidiet <code>php scripts/sync_rates.php</code>.</p>
<?php endif; ?>

<form method="get" action="index.php" class="panel rates-form">
    <input type="hidden" name="page" value="rates">
    <div class="field">
        <label for="from">No valūtas</label>
        <select id="from" name="from" aria-describedby="from-error"><?= $options($currencies, $form['from']) ?></select>
        <?= $err('from') ?>
    </div>
    <button type="button" id="swap" class="swap" aria-label="Apmainīt valūtas vietām" title="Apmainīt valūtas vietām">⇄</button>
    <div class="field">
        <label for="to">Uz valūtu</label>
        <select id="to" name="to" aria-describedby="to-error"><?= $options($currencies, $form['to']) ?></select>
        <?= $err('to') ?>
    </div>
    <div class="field">
        <label for="start">Sākuma datums</label>
        <input id="start" name="start" type="date" value="<?= e($form['start']) ?>" min="1999-01-04" max="<?= e(date('Y-m-d')) ?>" aria-describedby="start-error">
        <?= $err('start') ?>
    </div>
    <div class="field">
        <label for="end">Beigu datums</label>
        <input id="end" name="end" type="date" value="<?= e($form['end']) ?>" min="1999-01-04" max="<?= e(date('Y-m-d')) ?>" aria-describedby="end-error">
        <?= $err('end') ?>
    </div>
    <button type="submit" class="primary">Rādīt</button>
</form>

<?php if ($stats): ?>
    <?php $d = $digits($stats['last']); ?>
    <section class="panel">
        <h2><?= e($form['from']) ?> → <?= e($form['to']) ?></h2>
        <div class="chart-box">
            <canvas id="rateChart"
                    data-title="<?= e($form['from'] . ' → ' . $form['to']) ?>"
                    data-labels="<?= e(json_encode(array_keys($series))) ?>"
                    data-values="<?= e(json_encode(array_map(static fn(float $x): float => round($x, 6), array_values($series)))) ?>"
                    role="img" aria-label="Kursa izmaiņu diagramma"></canvas>
        </div>
        <dl class="stats">
            <div><dt>Pēdējais kurss</dt><dd><?= View::num($stats['last'], $d) ?></dd></div>
            <div><dt>Zemākais</dt><dd><?= View::num($stats['min'], $d) ?></dd></div>
            <div><dt>Augstākais</dt><dd><?= View::num($stats['max'], $d) ?></dd></div>
            <div><dt>Vidējais</dt><dd><?= View::num($stats['avg'], $d) ?></dd></div>
            <div><dt>Izmaiņa periodā</dt><dd><?= ($stats['change'] >= 0 ? '+' : '') . View::num($stats['change']) ?> %</dd></div>
        </dl>
    </section>

    <section>
        <h2>Dienas kursi</h2>
        <div class="table-scroll tall">
            <table>
                <thead><tr><th>Datums</th><th class="num">1 <?= e($form['from']) ?> = … <?= e($form['to']) ?></th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($series, true) as $date => $rate): ?>
                    <tr><td><?= e($date) ?></td><td class="num"><?= View::num($rate, $d) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php elseif (!$v->hasErrors()): ?>
    <p class="muted">Izvēlētajam periodam nav kursu datu. Izmēģiniet citu periodu vai atjauniet kursus.</p>
<?php endif; ?>

<?php if (Auth::isAdmin()): ?>
    <section class="panel admin">
        <h2>Administrēšana</h2>
        <p class="muted">Ielādē pēdējo 30 dienu kursus no Frankfurter API un aizstāj demonstrācijas datus.<?= $latestDate ? ' Jaunākais datums datubāzē: ' . e($latestDate) . '.' : '' ?></p>
        <form method="post" action="index.php?page=sync">
            <?= Csrf::field() ?>
            <button type="submit" class="primary">Atjaunot kursus</button>
        </form>
    </section>
<?php endif; ?>

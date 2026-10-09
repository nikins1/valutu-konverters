<?php
use App\Config;
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
/** Kursam rāda vairāk zīmju, ja tas ir ļoti mazs. */
$fmtRate = static fn(float $r): string => View::num($r, $r < 0.01 ? 6 : 4);
?>
<h1>Valūtu konvertētājs</h1>

<?php if ($latestDate === null): ?>
    <p class="notice warn">Datubāzē vēl nav kursu. Administrators tos var ielādēt sadaļā "Kursu vēsture" vai ar komandu <code>php scripts/sync_rates.php</code>.</p>
<?php elseif ($isDemo): ?>
    <p class="notice warn">Pašlaik tiek rādīti demonstrācijas kursi, nevis reāli. Lai ielādētu īstos, palaidiet <code>php scripts/sync_rates.php</code>.</p>
<?php endif; ?>

<?php if ($v->error('general')): ?>
    <p class="notice error" role="alert"><?= e($v->error('general')) ?></p>
<?php endif; ?>

<form method="post" action="index.php?page=converter" class="panel converter">
    <?= Csrf::field() ?>
    <div class="field">
        <label for="amount">Summa</label>
        <input id="amount" name="amount" type="text" inputmode="decimal" value="<?= e($old['amount']) ?>"
               pattern="\d{1,12}([.,]\d{1,2})?" maxlength="15" required aria-describedby="amount-hint amount-error">
        <p class="hint" id="amount-hint">Līdz <?= View::num((float) Config::get('cash_limit_eur'), 0) ?> EUR ekvivalentā.</p>
        <?= $err('amount') ?>
    </div>
    <div class="field">
        <label for="from">No valūtas</label>
        <select id="from" name="from" required aria-describedby="from-error"><?= $options($currencies, $old['from']) ?></select>
        <?= $err('from') ?>
    </div>
    <button type="button" id="swap" class="swap" aria-label="Apmainīt valūtas vietām" title="Apmainīt valūtas vietām">⇄</button>
    <div class="field">
        <label for="to">Uz valūtu</label>
        <select id="to" name="to" required aria-describedby="to-error"><?= $options($currencies, $old['to']) ?></select>
        <?= $err('to') ?>
    </div>
    <button type="submit" class="primary">Konvertēt</button>
</form>

<?php if ($result): ?>
    <section class="result" aria-live="polite">
        <p class="result-line"><?= View::num($result['amount']) ?> <?= e($result['from']) ?> =</p>
        <p class="result-figure"><?= View::num($result['result']) ?> <span><?= e($result['to']) ?></span></p>
        <p class="result-meta">
            1 <?= e($result['from']) ?> = <?= $fmtRate($result['rate']) ?> <?= e($result['to']) ?>
            (kursa datums: <?= e($result['rate_date']) ?>)
        </p>
    </section>
<?php endif; ?>

<section class="history">
    <div class="history-head">
        <h2>Jūsu pēdējās konvertācijas</h2>
        <?php if ($history): ?>
            <form method="post" action="index.php?page=clear" class="inline">
                <?= Csrf::field() ?>
                <button type="submit" class="link-button">Notīrīt vēsturi</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if (!$history): ?>
        <p class="muted">Vēl nav neviena ieraksta. Veiciet pirmo konvertāciju augstāk.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Laiks</th><th class="num">Summa</th><th class="num">Rezultāts</th><th class="num">Kurss</th><th>Kursa datums</th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= e($h['created_at']) ?></td>
                        <td class="num"><?= View::num((float) $h['amount']) ?> <?= e($h['from_code']) ?></td>
                        <td class="num"><?= View::num((float) $h['result']) ?> <?= e($h['to_code']) ?></td>
                        <td class="num"><?= $fmtRate((float) $h['rate_used']) ?></td>
                        <td><?= e($h['rate_date']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

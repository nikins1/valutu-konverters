<?php
use App\Security\Csrf;

/** Palīgfunkcija: rāda lauka kļūdu, ja tāda ir. */
$err = static function (string $field) use ($v): string {
    $m = $v->error($field);
    return $m === null ? '' : '<p class="field-error" id="' . $field . '-error" role="alert">' . e($m) . '</p>';
};
?>
<section class="panel narrow">
    <h1>Reģistrācija</h1>
    <form method="post" action="index.php?page=register">
        <?= Csrf::field() ?>
        <div class="field">
            <label for="username">Lietotājvārds</label>
            <input id="username" name="username" type="text" value="<?= e($old['username']) ?>" maxlength="30"
                   pattern="[A-Za-z0-9_]{3,30}" autocomplete="username" required aria-describedby="username-hint username-error">
            <p class="hint" id="username-hint">3–30 simboli: latīņu burti, cipari, pasvītra.</p>
            <?= $err('username') ?>
        </div>
        <div class="field">
            <label for="email">E-pasts (nav obligāts)</label>
            <input id="email" name="email" type="email" value="<?= e($old['email']) ?>" maxlength="254" autocomplete="email" aria-describedby="email-error">
            <?= $err('email') ?>
        </div>
        <div class="field">
            <label for="password">Parole</label>
            <input id="password" name="password" type="password" minlength="8" maxlength="64" autocomplete="new-password" required aria-describedby="password-hint password-error">
            <p class="hint" id="password-hint">8–64 simboli. Garāka parole ir drošāka.</p>
            <?= $err('password') ?>
        </div>
        <div class="field">
            <label for="password2">Atkārtojiet paroli</label>
            <input id="password2" name="password2" type="password" minlength="8" maxlength="64" autocomplete="new-password" required aria-describedby="password2-error">
            <?= $err('password2') ?>
        </div>
        <button type="submit" class="primary">Izveidot kontu</button>
    </form>
    <p class="muted">Jau ir konts? <a href="index.php?page=login">Pieteikties</a>.</p>
</section>

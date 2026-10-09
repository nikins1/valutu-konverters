<?php use App\Security\Csrf; ?>
<section class="panel narrow">
    <h1>Pieteikšanās</h1>
    <?php if ($error): ?>
        <p class="notice error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" action="index.php?page=login">
        <?= Csrf::field() ?>
        <div class="field">
            <label for="username">Lietotājvārds</label>
            <input id="username" name="username" type="text" value="<?= e($username) ?>" maxlength="30" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="password">Parole</label>
            <input id="password" name="password" type="password" maxlength="64" autocomplete="current-password" required>
        </div>
        <button type="submit" class="primary">Pieteikties</button>
    </form>
    <p class="muted">Nav konta? <a href="index.php?page=register">Izveidojiet to</a>.</p>
</section>

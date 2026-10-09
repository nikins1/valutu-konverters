<?php
/** Kopējais lapas izkārtojums. Mainīgie: $title, $content, neobligāti $withChart. */
use App\Security\Auth;
use App\Security\Csrf;
use App\View;

$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Valūtu konvertētājs') ?> – Valūtu konvertētājs</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="wrap topbar-inner">
        <a class="brand" href="index.php">Valūtu konvertētājs</a>
        <nav aria-label="Galvenā navigācija">
            <?php if ($user): ?>
                <a href="index.php?page=converter">Konvertētājs</a>
                <a href="index.php?page=rates">Kursu vēsture</a>
                <span class="who"><?= e($user['username']) ?></span>
                <form method="post" action="index.php?page=logout" class="inline">
                    <?= Csrf::field() ?>
                    <button type="submit" class="link-button">Iziet</button>
                </form>
            <?php else: ?>
                <a href="index.php?page=login">Pieteikties</a>
                <a href="index.php?page=register">Reģistrēties</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="wrap">
    <?php foreach (View::takeFlashes() as $f): ?>
        <p class="notice <?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></p>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="wrap footer">
    Kursu dati: Eiropas Centrālās bankas atsauces kursi, kas iegūti ar Frankfurter API. Kursi ir informatīvi, nevis tirdzniecības piedāvājums.
</footer>

<?php if (!empty($withChart)): ?>
    <script src="assets/vendor/chart.umd.js"></script>
<?php endif; ?>
<script src="assets/js/app.js"></script>
</body>
</html>

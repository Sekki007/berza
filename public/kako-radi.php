<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$pageTitle = 'Kako radi — KupiTelefon';
$pageDescription = 'Kako kupovati i prodavati telefone, tablete i pametne satove na KupiTelefon, i kako pronaći servis.';
$canonicalUrl = absoluteUrl('/kako-radi.php');
$activePage = 'oglasi';
$showSearch = true;

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/index.php">Početna</a> › Kako radi</div>

        <div class="form-card">
            <h2>Kako radi KupiTelefon</h2>
            <div class="detail-desc">
                <h3>1. Pretraži oglase</h3>
                <p>Filtriraj telefone, tablete, pametne satove, delove i servisne usluge po brendu, gradu i ceni.</p>

                <h3>2. Postavi oglas</h3>
                <p>Registruj se, uloguj se i objavi telefon, tablet, sat, rezervni deo ili servisnu uslugu.</p>

                <h3>3. Kontaktiraj prodavca ili servis</h3>
                <p>Pozovi direktno ili pošalji poruku kroz sistem.</p>
            </div>
            <a class="btn-call" href="/register.php" style="display:inline-block;margin-top:12px;">Kreni odmah</a>
        </div>

        <div class="form-card" style="margin-top:12px;">
            <h2>Saveti pre kupovine</h2>
            <div class="detail-desc">
                <p>U <a href="/vodici">vodičima</a> smo objasnili kako da proverite polovan telefon, kako da izbegnete prevaru i kada se isplati zamena ekrana.</p>
            </div>
            <a class="btn-call" href="/vodici" style="display:inline-block;margin-top:12px;">Pročitaj vodiče</a>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>

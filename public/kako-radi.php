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

        <div class="form-card" style="margin-top:12px;" id="widget">
            <h2>Ubaci oglase na svoj sajt</h2>
            <div class="detail-desc">
                <p>Ubaci ovaj kod na svoj sajt (blog, servis, shop) — prikazuje nasumične aktivne oglase sa KupiTelefon.rs. Klik vodi na oglas.</p>
                <p>Partner <strong>ne mora ništa da menja</strong>: sistem automatski prepozna domen sajta gde je widget (za Analytics). Opciono možeš dodati <code>&amp;ref=...</code> ako želiš ručni naziv.</p>
                <p style="margin-top:10px;"><strong>Kod (copy-paste):</strong></p>
                <pre style="margin-top:8px;padding:12px;background:#f5f7fa;border:1px solid var(--border);border-radius:8px;overflow:auto;font-size:12px;line-height:1.45;"><?= h('<iframe
  src="' . rtrim(appBaseUrl(), '/') . '/widget.php?limit=3"
  style="width:300px;height:600px;border:0;overflow:hidden;border-radius:12px"
  loading="lazy"
  title="KupiTelefon oglasi"
></iframe>') ?></pre>
                <p style="margin-top:10px;color:var(--text-muted);font-size:13px;">
                    Vertikalni banner (npr. sidebar): <code>300×600</code> ili <code>160×600</code>.
                    Opcije: <code>limit=1..6</code>,
                    <code>type=telefon|delovi|servis</code>,
                    <code>ref=opciono</code>.
                    Primer: <a href="/widget.php?limit=3" target="_blank" rel="noopener">/widget.php?limit=3</a>
                </p>
            </div>
        </div>
    </main>
</div>

<?php require __DIR__ . '/partials/layout-end.php'; ?>

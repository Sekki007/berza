<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/widget.php';
requireAdmin();

$pageTitle = 'Widget kodovi — Admin';
$activePage = 'nalog';
$showSearch = false;
$adminPage = 'widget';
$presets = widgetSizePresets();
$base = rtrim(appBaseUrl(), '/');

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap admin-wrap">
    <?php require __DIR__ . '/partials/admin-sidebar.php'; ?>
    <main class="content">
        <div class="breadcrumb"><a href="/dashboard.php">Admin</a> › Widget kodovi</div>
        <div class="form-card">
            <h2>Embed widget — gotovi kodovi</h2>
            <p class="form-hint" style="margin-top:6px;">
                Copy-paste iframe kod na partnerski sajt ili u članak. Partner ne mora da menja ništa —
                <code>ref</code> se automatski uzima sa domena. Preview otvara live widget.
            </p>
            <div class="form-hint" style="margin-top:10px;padding:10px 12px;background:#fff8e1;border:1px solid #ffe082;border-radius:8px;color:#856404;">
                <strong>Ako se ne vidi na drugom sajtu:</strong>
                <ul style="margin:6px 0 0;padding-left:18px;">
                    <li>WordPress: ubaci preko bloka <em>Custom HTML</em> / HTML (ne običan editor — skida iframe).</li>
                    <li>Koristi tačan HTTPS URL: <code>https://kupitelefon.rs/widget.php?...</code></li>
                    <li>Proveri da li partnerski sajt ima CSP koji blokira iframe (frame-src).</li>
                    <li>Test: otvori preview u novom tabu — ako tu radi, kod je OK.</li>
                </ul>
            </div>
        </div>

        <?php foreach ($presets as $size => $preset):
            $code = widgetEmbedCode($size, $base);
            $previewUrl = '/widget.php?size=' . rawurlencode($size) . '&limit=' . (int)$preset['limit'];
            $iframeW = $preset['width'] === '100%' ? '100%' : $preset['width'];
            $iframeH = (int)$preset['height'];
            ?>
            <div class="form-card" style="margin-top:12px;" id="size-<?= h($size) ?>">
                <div style="display:flex;flex-wrap:wrap;gap:8px 16px;align-items:baseline;justify-content:space-between;">
                    <div>
                        <h3 style="margin:0;font-size:16px;"><?= h($preset['label']) ?></h3>
                        <p class="form-hint" style="margin:4px 0 0;"><?= h($preset['use']) ?></p>
                    </div>
                    <a class="btn-sm" href="<?= h($previewUrl) ?>" target="_blank" rel="noopener">Otvori preview</a>
                </div>

                <div style="margin-top:12px;overflow:auto;background:#f5f7fa;border:1px solid var(--border);border-radius:10px;padding:12px;">
                    <iframe
                        src="<?= h($previewUrl) ?>"
                        style="width:<?= h($iframeW) ?>;height:<?= $iframeH ?>px;max-width:100%;border:0;border-radius:8px;display:block;background:#fff;"
                        loading="lazy"
                        title="Preview <?= h($preset['label']) ?>"
                    ></iframe>
                </div>

                <label class="form-hint" style="display:block;margin-top:12px;margin-bottom:4px;">HTML kod</label>
                <textarea
                    readonly
                    rows="6"
                    data-widget-code
                    style="width:100%;font-family:ui-monospace,Consolas,monospace;font-size:12px;line-height:1.4;padding:10px;border:1px solid var(--border);border-radius:8px;background:#fff;resize:vertical;"
                ><?= h($code) ?></textarea>
                <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="btn-sm btn-sm-primary" data-copy-widget>Kopiraj kod</button>
                    <span class="form-hint" data-copy-status hidden style="align-self:center;margin:0;color:var(--kp-green-dark);">Kopirano.</span>
                </div>
            </div>
        <?php endforeach; ?>
    </main>
</div>

<script>
(function () {
  document.querySelectorAll('[data-copy-widget]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var card = btn.closest('.form-card');
      if (!card) return;
      var ta = card.querySelector('[data-widget-code]');
      var status = card.querySelector('[data-copy-status]');
      if (!ta) return;
      var text = ta.value;
      function done() {
        if (status) {
          status.hidden = false;
          setTimeout(function () { status.hidden = true; }, 1600);
        }
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(function () {
          ta.focus(); ta.select();
          try { document.execCommand('copy'); done(); } catch (e) {}
        });
      } else {
        ta.focus(); ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
      }
    });
  });
})();
</script>

<?php require __DIR__ . '/partials/layout-end.php'; ?>

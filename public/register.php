<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $requestPath === '/register.php') {
    $query = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');
    $target = '/registracija' . ($query !== '' ? '?' . $query : '');
    header('Location: ' . $target, true, 301);
    exit;
}

if (isLoggedIn()) {
    header('Location: /nalog.php');
    exit;
}

$site = siteSettings();
if (empty($site['enable_registration'])) {
    setFlash('danger', 'Registracija trenutno nije omogućena.');
    header('Location: /login.php');
    exit;
}

$form = [
    'full_name' => '',
    'username' => '',
    'phone' => '',
    'email' => '',
];
$formError = '';
$acceptedTerms = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/register.php');
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $acceptedTerms = !empty($_POST['accept_terms']);

    $form = [
        'full_name' => $fullName,
        'username' => $username,
        'phone' => $phone,
        'email' => $email,
    ];

    $normalized = normalizePhoneRs($phone);
    if ($normalized === null || !isAllowedSmsPhone($normalized)) {
        $formError = 'Unesi validan srpski mobilni broj (npr. 06x xxx xxxx).';
    } elseif (strlen($password) < 6) {
        $formError = 'Lozinka mora imati najmanje 6 karaktera.';
    } elseif (mb_strlen($username) < 3) {
        $formError = 'Korisničko ime mora imati najmanje 3 karaktera.';
    } elseif (!$acceptedTerms) {
        $formError = 'Potvrdi da prihvataš Uslove korišćenja i Politiku privatnosti.';
    } elseif (findUserByUsername($username)) {
        $formError = 'Korisničko ime je zauzeto. Izaberi drugo.';
    } elseif (findUserByPhone($normalized)) {
        $formError = 'Ovaj broj telefona je već registrovan.';
    } else {
        $userId = registerUser($username, $password, $fullName, $phone);
        if ($userId === false) {
            $formError = 'Registracija nije uspela. Proveri podatke i pokušaj ponovo.';
        } else {
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                patchUser($userId, ['email' => $email]);
            }
            $_SESSION['pending_phone_verify_user_id'] = $userId;

            if (!smsEnabled()) {
                patchUser($userId, ['phone_verified_at' => date('Y-m-d H:i:s')]);
                unset($_SESSION['pending_phone_verify_user_id']);
                queueFacebookPixelEvent('CompleteRegistration', ['status' => 'auto']);
                queueGoogleTagEvent('sign_up', ['method' => 'phone_auto']);
                setFlash('success', 'Nalog je kreiran (SMS je isključen — telefon automatski označen kao potvrđen).');
                header('Location: /login.php');
                exit;
            }

            $otp = sendUserOtp($userId, 'phone_verify');
            if (!empty($otp['ok'])) {
                setFlash('success', 'Nalog je kreiran. Unesi SMS kod koji smo poslali.');
            } else {
                $fallbackSent = false;
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && function_exists('mailIsConfigured') && mailIsConfigured()) {
                    $emailOtp = sendUserOtp($userId, 'phone_verify', null, 'email', $email);
                    $fallbackSent = !empty($emailOtp['ok']);
                }
                if ($fallbackSent) {
                    setFlash('success', 'Nalog je kreiran. SMS trenutno ne radi, pa smo poslali OTP kod na email.');
                } else {
                    setFlash('danger', 'Nalog je kreiran, ali SMS nije poslat: ' . (string)($otp['error'] ?? 'greška') . '. Na verifikaciji možeš odmah izabrati slanje koda na email.');
                }
            }
            header('Location: /verify-phone.php');
            exit;
        }
    }
}

$pageTitle = 'Registracija — KupiTelefon';
$canonicalUrl = absoluteUrl('/registracija');
$activePage = 'nalog';
$minimalHeader = true;
$showSearch = false;

require __DIR__ . '/partials/layout-start.php';
?>

<div class="main-wrap">
    <main class="content">
        <div class="breadcrumb"><a href="/">Početna</a> › Registracija</div>
        <div class="form-card">
            <h2>Registracija</h2>
            <p style="font-size:14px;color:var(--text-muted);margin-bottom:16px;">
                Poslaćemo SMS kod na tvoj mobilni broj radi potvrde.
            </p>

            <?php if ($formError !== ''): ?>
                <p class="form-hint" style="color:#b91c1c;margin-bottom:12px;"><?= h($formError) ?></p>
            <?php endif; ?>

            <form method="POST" id="register-form" data-register-form novalidate>
                <?= csrfField() ?>
                <div class="form-group">
                    <label>Ime i prezime</label>
                    <input type="text" name="full_name" required value="<?= h($form['full_name']) ?>">
                </div>
                <div class="form-group">
                    <label>Korisničko ime</label>
                    <input type="text" name="username" id="register-username" required minlength="3" maxlength="40" autocomplete="username" value="<?= h($form['username']) ?>" data-username-check>
                    <p class="form-hint" id="username-status" style="margin-top:6px;" aria-live="polite"></p>
                </div>
                <div class="form-group">
                    <label>Mobilni telefon</label>
                    <input type="text" name="phone" required placeholder="06x xxx xxxx" value="<?= h($form['phone']) ?>">
                    <p class="form-hint" style="margin-top:6px;">Samo srpski mobilni brojevi (+3816…).</p>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= h($form['email']) ?>" placeholder="tvoj@email.com" autocomplete="email">
                </div>
                <div class="form-group">
                    <label>Lozinka</label>
                    <input type="password" name="password" required minlength="6" autocomplete="new-password">
                </div>
                <div class="form-group register-terms">
                    <label class="register-terms-label">
                        <input type="checkbox" name="accept_terms" value="1" <?= $acceptedTerms ? 'checked' : '' ?> required>
                        <span>
                            Prihvatam
                            <a href="/uslovi" target="_blank" rel="noopener">Uslove korišćenja</a>
                            i
                            <a href="/privatnost" target="_blank" rel="noopener">Politiku privatnosti</a>.
                        </span>
                    </label>
                </div>
                <button class="btn-call" type="submit" id="register-submit">Kreiraj nalog</button>
            </form>
            <p style="margin-top:14px;font-size:13px;color:var(--text-muted);">
                Već imaš nalog? <a href="/login.php">Prijavi se</a>
            </p>
        </div>
    </main>
</div>

<script>
(function () {
  var input = document.getElementById('register-username');
  var statusEl = document.getElementById('username-status');
  var form = document.getElementById('register-form');
  var submitBtn = document.getElementById('register-submit');
  if (!input || !statusEl || !form) return;

  var timer = null;
  var lastChecked = '';
  var available = null;

  function setStatus(text, color, isAvailable) {
    statusEl.textContent = text || '';
    statusEl.style.color = color || 'var(--text-muted)';
    available = isAvailable;
    if (submitBtn) {
      submitBtn.disabled = isAvailable === false;
    }
  }

  function checkUsername() {
    var value = (input.value || '').trim();
    if (value === lastChecked && available !== null) return;
    lastChecked = value;

    if (value.length === 0) {
      setStatus('', '', null);
      return;
    }
    if (value.length < 3) {
      setStatus('Korisničko ime mora imati najmanje 3 karaktera.', '#b45309', false);
      return;
    }

    setStatus('Proveravam…', 'var(--text-muted)', null);

    fetch('/api/check-username.php?username=' + encodeURIComponent(value), {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if ((input.value || '').trim() !== value) return;
        if (data.available === true) {
          setStatus(data.message || 'Dostupno.', 'var(--kp-green-dark, #15803d)', true);
        } else if (data.available === false) {
          setStatus(data.message || 'Zauzeto.', '#b91c1c', false);
        } else {
          setStatus('', '', null);
        }
      })
      .catch(function () {
        setStatus('Provera trenutno nije dostupna.', '#b45309', null);
      });
  }

  input.addEventListener('input', function () {
    available = null;
    if (submitBtn) submitBtn.disabled = false;
    clearTimeout(timer);
    timer = setTimeout(checkUsername, 350);
  });

  input.addEventListener('blur', checkUsername);

  form.addEventListener('submit', function (e) {
    var value = (input.value || '').trim();
    if (value.length < 3) {
      e.preventDefault();
      setStatus('Korisničko ime mora imati najmanje 3 karaktera.', '#b91c1c', false);
      input.focus();
      return;
    }
    if (available === false) {
      e.preventDefault();
      setStatus('Izaberi drugo korisničko ime — ovo je zauzeto.', '#b91c1c', false);
      input.focus();
    }
  });

  if ((input.value || '').trim().length >= 3) {
    checkUsername();
  }
})();
</script>

<?php require __DIR__ . '/partials/layout-end.php'; ?>

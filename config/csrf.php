<?php

declare(strict_types=1);

function csrfToken(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
}

function csrfMetaTag(): string
{
    return '<meta name="csrf-token" content="' . h(csrfToken()) . '">';
}

function verifyCsrf(?string $token = null): bool
{
    $session = (string)($_SESSION['_csrf'] ?? '');
    if ($session === '') {
        return false;
    }

    if ($token === null) {
        $token = (string)($_POST['_csrf'] ?? '');
        if ($token === '') {
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strtolower((string)$name) === 'x-csrf-token') {
                        $token = (string)$value;
                        break;
                    }
                }
            }
        }
        if ($token === '' && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
        }
    }

    return is_string($token) && $token !== '' && hash_equals($session, $token);
}

/**
 * Call at the start of POST handlers. Redirects back on failure for HTML forms.
 */
function requireCsrf(?string $redirectTo = null): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    if (verifyCsrf()) {
        return;
    }

    if (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || str_starts_with((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/api/')) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'error' => 'csrf'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    setFlash('danger', 'Sesija je istekla ili zahtev nije važeći. Pokušaj ponovo.');
    $target = $redirectTo;
    if ($target === null || $target === '') {
        $target = (string)($_SERVER['HTTP_REFERER'] ?? '/index.php');
        if ($target === '' || !str_starts_with($target, '/')) {
            // allow same-host referer
            $host = (string)($_SERVER['HTTP_HOST'] ?? '');
            $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
            if ($host !== '' && $ref !== '' && str_contains($ref, $host)) {
                $target = $ref;
            } else {
                $target = '/index.php';
            }
        }
    }
    header('Location: ' . $target);
    exit;
}

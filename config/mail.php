<?php

declare(strict_types=1);

/**
 * SMTP konfiguracija iz .env (Zoho Mail).
 */
function mailSmtpConfig(): array
{
    return [
        'enabled' => strtolower(trim((string)envValue('SMTP_ENABLED', 'true'))) !== 'false',
        'host' => trim((string)envValue('SMTP_HOST', 'smtp.zoho.eu')),
        'port' => (int)envValue('SMTP_PORT', '587'),
        'encryption' => strtolower(trim((string)envValue('SMTP_ENCRYPTION', 'tls'))), // tls | ssl | none
        'username' => trim((string)envValue('SMTP_USERNAME', 'podrska@kupitelefon.rs')),
        'password' => (string)(envValue('ZOHO_SMTP_PASSWORD', '') ?: envValue('SMTP_PASSWORD', '')),
        'from_email' => trim((string)envValue('SMTP_FROM_EMAIL', 'podrska@kupitelefon.rs')),
        'from_name' => trim((string)envValue('SMTP_FROM_NAME', 'KupiTelefon.rs')),
        'timeout' => max(5, (int)envValue('SMTP_TIMEOUT', '20')),
    ];
}

function mailIsConfigured(): bool
{
    $c = mailSmtpConfig();
    return $c['enabled']
        && $c['host'] !== ''
        && $c['username'] !== ''
        && $c['password'] !== ''
        && filter_var($c['from_email'], FILTER_VALIDATE_EMAIL);
}

function mailStatusSummary(): array
{
    $c = mailSmtpConfig();
    $ok = mailIsConfigured();
    return [
        'ok' => $ok,
        'host' => $c['host'],
        'port' => $c['port'],
        'encryption' => $c['encryption'],
        'username' => $c['username'],
        'from_email' => $c['from_email'],
        'from_name' => $c['from_name'],
        'has_password' => $c['password'] !== '',
        'message' => $ok
            ? 'SMTP spreman (' . $c['host'] . ':' . $c['port'] . ')'
            : 'SMTP nije podešen — dodaj ZOHO_SMTP_PASSWORD (i ostalo) u .env',
    ];
}

/**
 * @return array{ok:bool,error:?string}
 */
function sendSmtpEmail(string $toEmail, string $subject, string $body, ?string $toName = null): array
{
    $toEmail = trim($toEmail);
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Neispravna adresa primaoca.'];
    }
    if (!mailIsConfigured()) {
        return ['ok' => false, 'error' => 'SMTP nije konfigurisan (.env).'];
    }

    $c = mailSmtpConfig();
    $fromEmail = $c['from_email'];
    $fromName = $c['from_name'] !== '' ? $c['from_name'] : 'KupiTelefon.rs';

    $errno = 0;
    $errstr = '';
    $remote = ($c['encryption'] === 'ssl' ? 'ssl://' : '') . $c['host'] . ':' . $c['port'];
    $socket = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        $c['timeout'],
        STREAM_CLIENT_CONNECT,
        stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ])
    );

    if (!$socket) {
        return ['ok' => false, 'error' => "Ne mogu da se povežem na SMTP ({$errno}): {$errstr}"];
    }

    stream_set_timeout($socket, $c['timeout']);

    $read = static function () use ($socket): string {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $expect = static function (string $response, array $codes) use (&$socket): ?string {
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            return 'Neočekivan SMTP odgovor: ' . trim($response);
        }
        return null;
    };

    $write = static function (string $cmd) use ($socket): void {
        fwrite($socket, $cmd . "\r\n");
    };

    try {
        $err = $expect($read(), [220]);
        if ($err) {
            throw new RuntimeException($err);
        }

        $domain = 'kupitelefon.rs';
        $write('EHLO ' . $domain);
        $err = $expect($read(), [250]);
        if ($err) {
            throw new RuntimeException($err);
        }

        if ($c['encryption'] === 'tls') {
            $write('STARTTLS');
            $err = $expect($read(), [220]);
            if ($err) {
                throw new RuntimeException($err);
            }
            $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoOk !== true) {
                throw new RuntimeException('STARTTLS nije uspeo (OpenSSL?).');
            }
            $write('EHLO ' . $domain);
            $err = $expect($read(), [250]);
            if ($err) {
                throw new RuntimeException($err);
            }
        }

        $write('AUTH LOGIN');
        $err = $expect($read(), [334]);
        if ($err) {
            throw new RuntimeException($err);
        }
        $write(base64_encode($c['username']));
        $err = $expect($read(), [334]);
        if ($err) {
            throw new RuntimeException($err);
        }
        $write(base64_encode($c['password']));
        $err = $expect($read(), [235]);
        if ($err) {
            throw new RuntimeException('SMTP autentifikacija nije uspela. Proveri ZOHO_SMTP_PASSWORD / Application Password.');
        }

        $write('MAIL FROM:<' . $fromEmail . '>');
        $err = $expect($read(), [250]);
        if ($err) {
            throw new RuntimeException($err);
        }

        $write('RCPT TO:<' . $toEmail . '>');
        $err = $expect($read(), [250, 251]);
        if ($err) {
            throw new RuntimeException($err);
        }

        $write('DATA');
        $err = $expect($read(), [354]);
        if ($err) {
            throw new RuntimeException($err);
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $date = date('r');
        $messageId = sprintf('<%s.%s@%s>', bin2hex(random_bytes(8)), time(), $domain);

        $headers = [
            'Date: ' . $date,
            'From: ' . $encodedFromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'To: ' . ($toName ? ('=?UTF-8?B?' . base64_encode($toName) . '?= <' . $toEmail . '>') : $toEmail),
            'Subject: ' . $encodedSubject,
            'Message-ID: ' . $messageId,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'X-Mailer: KupiTelefon',
        ];

        // Dot-stuffing for body lines starting with .
        $b64 = chunk_split(base64_encode($body));
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $b64 . "\r\n.";
        $write($payload);
        $err = $expect($read(), [250]);
        if ($err) {
            throw new RuntimeException($err);
        }

        $write('QUIT');
        $read();
        fclose($socket);

        mailLog(true, $toEmail, $subject, null);
        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        if (is_resource($socket)) {
            fclose($socket);
        }
        mailLog(false, $toEmail, $subject, $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function mailLog(bool $ok, string $to, string $subject, ?string $error): void
{
    try {
        $path = dataPath('mail_log.json');
        $items = [];
        if (file_exists($path)) {
            $decoded = json_decode((string)file_get_contents($path), true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }
        $items[] = [
            'ok' => $ok,
            'to' => $to,
            'subject' => mb_substr($subject, 0, 160),
            'error' => $error,
            'at' => date('Y-m-d H:i:s'),
        ];
        if (count($items) > 100) {
            $items = array_slice($items, -100);
        }
        file_put_contents($path, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } catch (Throwable $e) {
        // ignore logging failures
    }
}

<?php

declare(strict_types=1);

/**
 * Podrazumevani email šabloni. Placeholders: {name} {code} {title} {body} {link} {site} {url} …
 */
function defaultEmailTemplates(): array
{
    $site = 'KupiTelefon';
    return [
        'otp_phone_verify' => [
            'label' => 'OTP — verifikacija telefona (email fallback)',
            'subject' => '{site}: kod za verifikaciju',
            'body' => "Zdravo{name_hello},\n\nTvoj kod za verifikaciju je: {code}\n\nVaži 10 minuta.\nAko nisi tražio/la ovaj kod, ignoriši poruku.\n\n—\n{site}\n{url}",
            'vars' => '{code} {name} {name_hello} {site} {url}',
        ],
        'otp_password_reset' => [
            'label' => 'OTP — reset lozinke (email fallback)',
            'subject' => '{site}: kod za reset lozinke',
            'body' => "Zdravo{name_hello},\n\nTvoj kod za reset lozinke je: {code}\n\nVaži 10 minuta.\nAko nisi tražio/la ovaj kod, ignoriši poruku.\n\n—\n{site}\n{url}",
            'vars' => '{code} {name} {name_hello} {site} {url}',
        ],
        'email_verify' => [
            'label' => 'Potvrda email adrese',
            'subject' => '{site}: potvrdi email',
            'body' => "Zdravo{name_hello},\n\nPotvrdi svoj email klikom na link:\n{link}\n\nAko nisi tražio/la ovo, ignoriši poruku.\n\n—\n{site}\n{url}",
            'vars' => '{link} {name} {name_hello} {site} {url}',
        ],
        'new_message' => [
            'label' => 'Nova poruka',
            'subject' => '{site}: {title}',
            'body' => "Zdravo{name_hello},\n\n{body}\n\nOtvori poruke:\n{link}\n\n—\n{site}\n{url}",
            'vars' => '{title} {body} {link} {name} {name_hello} {site} {url}',
        ],
        'ad_expiry_warning' => [
            'label' => 'Upozorenje — oglas uskoro ističe',
            'subject' => '{site}: {title}',
            'body' => "Zdravo{name_hello},\n\n{body}\n\nUpravljaj oglasima:\n{link}\n\n—\n{site}\n{url}",
            'vars' => '{title} {body} {link} {name} {name_hello} {site} {url}',
        ],
        'ad_expired' => [
            'label' => 'Oglas je istekao',
            'subject' => '{site}: {title}',
            'body' => "Zdravo{name_hello},\n\n{body}\n\nProduži oglas ovde:\n{link}\n\n—\n{site}\n{url}",
            'vars' => '{title} {body} {link} {name} {name_hello} {site} {url}',
        ],
        'saved_search_match' => [
            'label' => 'Alert — sačuvana pretraga',
            'subject' => '{site}: {title}',
            'body' => "Zdravo{name_hello},\n\n{body}\n\nPogledaj rezultate:\n{link}\n\n—\n{site}\n{url}",
            'vars' => '{title} {body} {link} {name} {name_hello} {site} {url}',
        ],
        'notification' => [
            'label' => 'Opšte obaveštenje (ostali tipovi)',
            'subject' => '{site}: {title}',
            'body' => "Zdravo{name_hello},\n\n{body}\n\n{link_block}—\n{site}\n{url}",
            'vars' => '{title} {body} {link} {link_block} {name} {name_hello} {site} {url}',
        ],
    ];
}

function emailTemplatesMerged(): array
{
    $defaults = defaultEmailTemplates();
    $stored = siteSettings()['email_templates'] ?? [];
    if (!is_array($stored)) {
        $stored = [];
    }
    $out = [];
    foreach ($defaults as $key => $def) {
        $row = is_array($stored[$key] ?? null) ? $stored[$key] : [];
        $subject = trim((string)($row['subject'] ?? $def['subject']));
        $body = trim((string)($row['body'] ?? $def['body']));
        if ($subject === '') {
            $subject = (string)$def['subject'];
        }
        if ($body === '') {
            $body = (string)$def['body'];
        }
        $out[$key] = [
            'label' => (string)$def['label'],
            'subject' => $subject,
            'body' => $body,
            'vars' => (string)$def['vars'],
        ];
    }
    return $out;
}

/**
 * @param array<string, string|int|float|null> $vars
 * @return array{subject: string, body: string}
 */
function renderEmailTemplate(string $key, array $vars = []): array
{
    $templates = emailTemplatesMerged();
    if (!isset($templates[$key])) {
        $key = 'notification';
    }
    $tpl = $templates[$key];

    $site = (string)(siteSettings()['site_name'] ?? 'KupiTelefon');
    $url = appBaseUrl();
    $name = trim((string)($vars['name'] ?? ''));
    $nameHello = $name !== '' ? (' ' . $name) : '';
    $link = trim((string)($vars['link'] ?? ''));
    if ($link !== '' && !preg_match('#^https?://#i', $link)) {
        $link = rtrim($url, '/') . '/' . ltrim($link, '/');
    }
    $linkBlock = $link !== '' ? ("Otvori:\n{$link}\n\n") : '';

    $map = [
        '{site}' => $site,
        '{url}' => $url,
        '{name}' => $name,
        '{name_hello}' => $nameHello,
        '{code}' => (string)($vars['code'] ?? ''),
        '{title}' => (string)($vars['title'] ?? ''),
        '{body}' => (string)($vars['body'] ?? ''),
        '{link}' => $link,
        '{link_block}' => $linkBlock,
    ];

    foreach ($vars as $k => $v) {
        if (is_scalar($v) || $v === null) {
            $map['{' . $k . '}'] = (string)$v;
        }
    }

    $subject = strtr((string)$tpl['subject'], $map);
    $body = strtr((string)$tpl['body'], $map);
    // Cleanup leftover empty placeholders
    $subject = preg_replace('/\{[a-z_]+\}/i', '', $subject) ?? $subject;
    $body = preg_replace('/\{[a-z_]+\}/i', '', $body) ?? $body;

    return [
        'subject' => trim($subject),
        'body' => trim($body),
    ];
}

/**
 * Normalizuje POST email_templates iz admin forme.
 *
 * @param array<string, mixed> $posted
 * @return array<string, array{subject: string, body: string}>
 */
function parseEmailTemplatesPost(array $posted): array
{
    $defaults = defaultEmailTemplates();
    $out = [];
    foreach ($defaults as $key => $def) {
        $subject = trim((string)($posted[$key]['subject'] ?? $def['subject']));
        $body = trim((string)($posted[$key]['body'] ?? $def['body']));
        if ($subject === '') {
            $subject = (string)$def['subject'];
        }
        if ($body === '') {
            $body = (string)$def['body'];
        }
        // Limit size
        if (mb_strlen($subject) > 180) {
            $subject = mb_substr($subject, 0, 180);
        }
        if (mb_strlen($body) > 5000) {
            $body = mb_substr($body, 0, 5000);
        }
        $out[$key] = [
            'subject' => $subject,
            'body' => $body,
        ];
    }
    return $out;
}

<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/vodici';
require_once dirname(__DIR__) . '/config/bootstrap.php';

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    echo ($ok ? 'OK  ' : 'FAIL ') . $message . "\n";
    if (!$ok) {
        $failed++;
    }
};

$drafts = draftGuideTopics();
$check(count($drafts) === 20, 'ima 20 nacrta');
$publishedSlugs = array_map(static fn($g) => (string)($g['slug'] ?? ''), getPublishedGuides());
foreach ($drafts as $draft) {
    $check(($draft['status'] ?? '') === 'draft', $draft['slug'] . ' je draft');
    $check(!in_array($draft['slug'], $publishedSlugs, true), $draft['slug'] . ' nije objavljen');
    $check(str_contains((string)$draft['body_html'], 'nije napisan'), $draft['slug'] . ' nema izmišljen tekst');
}
$check(guideLegacyBlogTarget('/blog') === '/vodici', '/blog → /vodici');
$check(guideLegacyBlogTarget('/blog/icloud-lock-provera') === '/vodic/icloud-lock-provera', 'slug ide na /vodic');

echo $failed === 0 ? "SVE PROVERE PROŠLE\n" : ($failed . " PALO\n");
exit($failed === 0 ? 0 : 1);

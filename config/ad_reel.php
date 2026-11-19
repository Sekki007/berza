<?php

declare(strict_types=1);

/**
 * Auto reel (9:16) — Ken Burns video + jasan srpski TTS.
 */

function adReelDir(int $adId): string
{
    $dir = uploadsDir() . '/' . max(1, $adId) . '/reel';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function adReelPublicPath(int $adId): string
{
    return '/uploads/ads/' . max(1, $adId) . '/reel/reel.mp4';
}

function adReelFilesystemPath(int $adId): string
{
    return adReelDir($adId) . '/reel.mp4';
}

function adReelFfmpegBinary(): string
{
    $env = trim((string)(getenv('FFMPEG_PATH') ?: ''));
    if ($env !== '' && is_file($env)) {
        return $env;
    }
    if (PHP_OS_FAMILY === 'Windows') {
        $candidates = [
            'C:\\Users\\daki!\\AppData\\Local\\Microsoft\\WinGet\\Packages\\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\\ffmpeg-9.0.2-full_build\\bin\\ffmpeg.exe',
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
        ];
        foreach ($candidates as $c) {
            if (is_file($c)) {
                return $c;
            }
        }
    }
    return 'ffmpeg';
}

function adReelFfprobeBinary(): string
{
    $ff = adReelFfmpegBinary();
    if (str_ends_with(strtolower($ff), 'ffmpeg.exe')) {
        return preg_replace('/ffmpeg\.exe$/i', 'ffprobe.exe', $ff) ?: 'ffprobe';
    }
    return str_replace('ffmpeg', 'ffprobe', $ff);
}

function adReelPythonBinary(): string
{
    $env = trim((string)(getenv('PYTHON_PATH') ?: ''));
    if ($env !== '') {
        return $env;
    }
    return PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
}

/** @return list<string> */
function adReelImageFiles(array $ad): array
{
    if (function_exists('shareCardAdImageFiles')) {
        return shareCardAdImageFiles($ad);
    }
    return [];
}

/** Broj 0–9999 → srpski (za jasan TTS). */
function adReelSpeakInt(int $n): string
{
    $n = max(0, min(9999, $n));
    $ones = ['nula', 'jedan', 'dva', 'tri', 'četiri', 'pet', 'šest', 'sedam', 'osam', 'devet'];
    $teens = ['deset', 'jedanaest', 'dvanaest', 'trinaest', 'četrnaest', 'petnaest', 'šesnaest', 'sedamnaest', 'osamnaest', 'devetnaest'];
    $tens = ['', '', 'dvadeset', 'trideset', 'četrdeset', 'pedeset', 'šezdeset', 'sedamdeset', 'osamdeset', 'devedeset'];
    $hundreds = ['', 'sto', 'dvesta', 'trista', 'četiristo', 'petsto', 'šesto', 'sedamsto', 'osamsto', 'devetsto'];

    if ($n < 10) {
        return $ones[$n];
    }
    if ($n < 20) {
        return $teens[$n - 10];
    }
    if ($n < 100) {
        $t = intdiv($n, 10);
        $o = $n % 10;
        return $o === 0 ? $tens[$t] : ($tens[$t] . ' ' . $ones[$o]);
    }
    if ($n < 1000) {
        $h = intdiv($n, 100);
        $rest = $n % 100;
        return $rest === 0 ? $hundreds[$h] : ($hundreds[$h] . ' ' . adReelSpeakInt($rest));
    }
    $th = intdiv($n, 1000);
    $rest = $n % 1000;
    $head = match ($th) {
        1 => 'hiljadu',
        2 => 'dve hiljade',
        3, 4 => adReelSpeakInt($th) . ' hiljade',
        default => adReelSpeakInt($th) . ' hiljada',
    };
    return $rest === 0 ? $head : ($head . ' ' . adReelSpeakInt($rest));
}

function adReelSpeakPriceAmount(array $ad): string
{
    $raw = function_exists('adCardPriceMainLabel') ? adCardPriceMainLabel($ad) : formatAdPrice($ad);
    if ($raw === '' || stripos($raw, 'dogovor') !== false) {
        return 'po dogovoru';
    }
    if (preg_match('/(\d[\d\s\.]*)/', $raw, $m)) {
        $num = (int)preg_replace('/[^\d]/', '', $m[1]);
        if ($num > 0) {
            return adReelSpeakInt($num) . ' evra';
        }
    }
    return 'po dogovoru';
}

function adReelSpeakStorage(?string $storage): string
{
    $storage = trim((string)$storage);
    if ($storage === '') {
        return '';
    }
    if (preg_match('/(\d+)\s*(gb|tb)/i', $storage, $m)) {
        $n = (int)$m[1];
        $unit = strtolower($m[2]) === 'tb' ? 'terabajta' : 'gigabajta';
        return adReelSpeakInt($n) . ' ' . $unit;
    }
    return mb_strtolower($storage);
}

function adReelSpeakModel(string $brand, string $model): string
{
    $full = trim($brand . ' ' . $model);
    $s = mb_strtolower($full);
    $map = [
        'iphone' => 'ajfon',
        'apple' => '',
        'samsung' => 'samsung',
        'galaxy' => 'galaksi',
        'xiaomi' => 'sjaomi',
        'huawei' => 'huavej',
        'google' => 'google',
        'pixel' => 'piksel',
        'honor' => 'honor',
        'oneplus' => 'van plas',
        'motorola' => 'motorola',
        'nokia' => 'nokija',
        'pro' => 'pro',
        'max' => 'maks',
        'ultra' => 'ultra',
        'plus' => 'plus',
        'mini' => 'mini',
        'fe' => 'ef e',
    ];
    foreach ($map as $from => $to) {
        $s = preg_replace('/\b' . preg_quote($from, '/') . '\b/ui', $to, $s) ?? $s;
    }
    $s = preg_replace_callback('/\b(\d{1,4})\b/u', static fn($m) => adReelSpeakInt((int)$m[1]), $s) ?? $s;
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    return $s !== '' ? $s : 'telefon';
}

/** Fonetski srpski tekst za TTS (bez engleskih skraćenica). */
function adReelNarration(array $ad): string
{
    $brand = trim((string)($ad['brand'] ?? ''));
    $model = trim((string)($ad['model'] ?? ''));
    $loc = trim((string)($ad['location'] ?? ''));
    $condition = trim((string)($ad['condition_state'] ?? ''));
    $type = function_exists('getAdType') ? getAdType($ad) : (string)($ad['ad_type'] ?? 'telefon');
    $spokenModel = adReelSpeakModel($brand, $model);
    $spokenPrice = adReelSpeakPriceAmount($ad);
    $spokenStorage = adReelSpeakStorage((string)($ad['storage'] ?? ''));

    $parts = [];
    if ($type === 'telefon') {
        $parts[] = 'Na Kupi telefonu je nov oglas.';
        $parts[] = 'U pitanju je ' . $spokenModel . '.';
    } elseif ($type === 'delovi') {
        $parts[] = 'Na Kupi telefonu je oglas za deo.';
        $parts[] = $spokenModel . '.';
    } else {
        $parts[] = 'Na Kupi telefonu je oglas za servis.';
        $parts[] = $spokenModel . '.';
    }

    $facts = [];
    if ($spokenStorage !== '') {
        $facts[] = 'memoriju ' . $spokenStorage;
    }
    if (isset($ad['battery_health']) && $ad['battery_health'] !== '' && $ad['battery_health'] !== null) {
        $facts[] = 'baterija ' . adReelSpeakInt((int)$ad['battery_health']) . ' posto';
    }
    if ($condition !== '') {
        $facts[] = 'stanje ' . mb_strtolower($condition);
    }
    if ($facts !== []) {
        $parts[] = 'Ima ' . implode(', ', $facts) . '.';
    }

    $parts[] = 'Cena je ' . $spokenPrice . '.';
    if ($loc !== '') {
        $parts[] = 'Nalazi se u mestu ' . $loc . '.';
    }
    $parts[] = 'Otvori oglas na sajtu Kupi telefon, i javi se prodavcu.';

    return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '');
}

function adReelLoadImage(string $path): ?\GdImage
{
    if (!is_file($path)) {
        return null;
    }
    $info = @getimagesize($path);
    if (!is_array($info)) {
        return null;
    }
    return match ((int)($info[2] ?? 0)) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
        IMAGETYPE_GIF => @imagecreatefromgif($path),
        default => null,
    };
}

function adReelPasteCover(\GdImage $canvas, \GdImage $src, int $dx, int $dy, int $dw, int $dh): void
{
    if (function_exists('shareCardPasteCover')) {
        shareCardPasteCover($canvas, $src, $dx, $dy, $dw, $dh);
        return;
    }
    imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, imagesx($src), imagesy($src));
}

function adReelDrawText(\GdImage $img, string $text, int $x, int $y, int $size, int $color, bool $bold = true): void
{
    if (function_exists('shareCardDrawText')) {
        shareCardDrawText($img, $text, $x, $y, $size, $color, $bold);
        return;
    }
    imagestring($img, 5, $x, max(0, $y - 14), $text, $color);
}

function adReelTextWidth(string $text, int $size, bool $bold = true): int
{
    if (function_exists('shareCardTextWidth')) {
        return shareCardTextWidth($text, $size, $bold);
    }
    return max(8, (int)round(mb_strlen($text) * $size * 0.55));
}

/** @return list<string> */
function adReelWrap(string $text, int $size, int $maxWidth, int $maxLines, bool $bold = true): array
{
    if (function_exists('shareCardWrapLines')) {
        return shareCardWrapLines($text, $size, $maxWidth, $maxLines, $bold);
    }
    return [mb_substr($text, 0, 40)];
}

/** Pripremi JPEG bazu za Ken Burns (veća od 1080×1920). */
function adReelPrepareBasePhoto(string $photoPath, string $destJpg): bool
{
    $src = adReelLoadImage($photoPath);
    if (!$src instanceof \GdImage) {
        return false;
    }
    $W = 1400;
    $H = 2489;
    $canvas = imagecreatetruecolor($W, $H);
    if ($canvas === false) {
        imagedestroy($src);
        return false;
    }
    $bg = imagecolorallocate($canvas, 8, 10, 9);
    imagefilledrectangle($canvas, 0, 0, $W, $H, $bg);
    adReelPasteCover($canvas, $src, 0, 0, $W, $H);
    imagedestroy($src);
    $ok = imagejpeg($canvas, $destJpg, 90);
    imagedestroy($canvas);
    return (bool)$ok;
}

/**
 * Providni overlay (samo UI) — ispod se vidi Ken Burns video.
 * @param 'hero'|'facts'|'outro' $variant
 */
function adReelRenderOverlay(array $ad, string $variant, string $destPng): bool
{
    $W = 1080;
    $H = 1920;
    $canvas = imagecreatetruecolor($W, $H);
    if ($canvas === false) {
        return false;
    }
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefilledrectangle($canvas, 0, 0, $W, $H, $transparent);
    imagealphablending($canvas, true);

    $white = imagecolorallocate($canvas, 255, 255, 255);
    $green = imagecolorallocate($canvas, 45, 122, 62);
    $greenDark = imagecolorallocatealpha($canvas, 20, 70, 35, 30);
    $yellow = imagecolorallocate($canvas, 245, 197, 24);
    $muted = imagecolorallocate($canvas, 220, 224, 218);
    $bar = imagecolorallocatealpha($canvas, 10, 14, 12, 40);
    $soft = imagecolorallocatealpha($canvas, 0, 0, 0, 55);

    $price = function_exists('adCardPriceMainLabel') ? adCardPriceMainLabel($ad) : formatAdPrice($ad);
    $loc = trim((string)($ad['location'] ?? ''));
    $title = trim(function_exists('adDisplayTitle') ? adDisplayTitle($ad) : (string)($ad['title'] ?? 'Oglas'));

    // top brand
    imagefilledrectangle($canvas, 0, 0, $W, 96, $green);
    imagefilledrectangle($canvas, 0, 96, $W, 104, $yellow);
    adReelDrawText($canvas, 'KupiTelefon.rs', 48, 64, 34, $white, true);

    // price bar
    imagefilledrectangle($canvas, 36, 124, 1044, 210, $greenDark);
    adReelDrawText($canvas, 'CENA', 60, 184, 22, $yellow, true);
    adReelDrawText($canvas, $price !== '' ? $price : 'Po dogovoru', 170, 188, 36, $white, true);
    if ($loc !== '') {
        $lw = adReelTextWidth($loc, 24, true);
        adReelDrawText($canvas, $loc, 1040 - $lw - 48, 186, 24, $muted, true);
    }

    if ($variant !== 'outro') {
        // bottom info panel
        imagefilledrectangle($canvas, 0, 1480, $W, $H, $bar);
        imagefilledrectangle($canvas, 0, 1470, $W, 1480, $yellow);

        $y = 1550;
        $lines = adReelWrap($title, 34, 960, 3, true);
        foreach ($lines as $line) {
            adReelDrawText($canvas, $line, 56, $y, 34, $white, true);
            $y += 48;
        }

        $badges = array_values(array_filter([
            trim((string)($ad['storage'] ?? '')),
            (isset($ad['battery_health']) && $ad['battery_health'] !== '' && $ad['battery_health'] !== null)
                ? ((int)$ad['battery_health'] . '% BH') : '',
            trim((string)($ad['condition_state'] ?? '')),
        ]));
        $bx = 56;
        $by = min($y + 24, 1820);
        foreach (array_slice($badges, 0, 4) as $badge) {
            $tw = adReelTextWidth($badge, 22, true) + 36;
            if ($bx + $tw > 1020) {
                break;
            }
            imagefilledrectangle($canvas, $bx, $by - 32, $bx + $tw, $by + 12, $green);
            adReelDrawText($canvas, $badge, $bx + 18, $by, 22, $white, true);
            $bx += $tw + 12;
        }

        if ($variant === 'facts') {
            adReelDrawText($canvas, 'Detalji i kontakt na sajtu', 56, 1888, 24, $yellow, true);
        }
    } else {
        imagefilledrectangle($canvas, 80, 720, $W - 80, 1280, $soft);
        imagefilledrectangle($canvas, 100, 760, $W - 100, 1240, $green);
        imagefilledrectangle($canvas, 100, 1240, $W - 100, 1250, $yellow);
        $cta1 = 'KupiTelefon.rs';
        adReelDrawText($canvas, $cta1, (int)(($W - adReelTextWidth($cta1, 48, true)) / 2), 920, 48, $white, true);
        $cta2 = 'Otvori oglas i javi se';
        adReelDrawText($canvas, $cta2, (int)(($W - adReelTextWidth($cta2, 30, true)) / 2), 1020, 30, $yellow, true);
        if ($price !== '') {
            adReelDrawText($canvas, $price, (int)(($W - adReelTextWidth($price, 40, true)) / 2), 1140, 40, $white, true);
        }
    }

    $ok = imagepng($canvas, $destPng, 6);
    imagedestroy($canvas);
    return (bool)$ok;
}

function adReelRun(array $cmd, ?string &$stdout = null, ?string &$stderr = null): int
{
    $tmp = sys_get_temp_dir() . '/kt_reel_' . bin2hex(random_bytes(4));
    $outFile = $tmp . '.out';
    $errFile = $tmp . '.err';
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', $outFile, 'w'],
        2 => ['file', $errFile, 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proc)) {
        $stderr = 'proc_open failed';
        @unlink($outFile);
        @unlink($errFile);
        return 1;
    }
    fclose($pipes[0]);
    $code = proc_close($proc);
    $stdout = is_file($outFile) ? (string)file_get_contents($outFile) : '';
    $stderr = is_file($errFile) ? (string)file_get_contents($errFile) : '';
    @unlink($outFile);
    @unlink($errFile);
    return $code;
}

/**
 * @return array{ok:bool,path?:string,public?:string,error?:string,script?:string,duration?:float}
 */
function generateAdReel(array $ad, bool $force = false): array
{
    $adId = (int)($ad['id'] ?? 0);
    if ($adId <= 0) {
        return ['ok' => false, 'error' => 'bad_ad'];
    }

    $outFs = adReelFilesystemPath($adId);
    $outPub = adReelPublicPath($adId);
    if (!$force && is_file($outFs) && filesize($outFs) > 50_000) {
        return ['ok' => true, 'path' => $outFs, 'public' => $outPub];
    }

    $images = adReelImageFiles($ad);
    if ($images === []) {
        return ['ok' => false, 'error' => 'no_images'];
    }

    $dir = adReelDir($adId);
    $photo = $images[0];
    $baseJpg = $dir . '/base.jpg';
    $ovHero = $dir . '/overlay_hero.png';
    $ovFacts = $dir . '/overlay_facts.png';
    $ovOutro = $dir . '/overlay_outro.png';
    $voice = $dir . '/voice.mp3';
    $scriptPath = $dir . '/script.txt';
    $metaPath = $dir . '/meta.txt';

    if (!adReelPrepareBasePhoto($photo, $baseJpg)) {
        return ['ok' => false, 'error' => 'base_photo_failed'];
    }
    if (!adReelRenderOverlay($ad, 'hero', $ovHero)
        || !adReelRenderOverlay($ad, 'facts', $ovFacts)
        || !adReelRenderOverlay($ad, 'outro', $ovOutro)) {
        return ['ok' => false, 'error' => 'overlay_failed'];
    }

    $script = adReelNarration($ad);
    file_put_contents($scriptPath, $script);

    $ttsPy = dirname(__DIR__) . '/tools/ad_reel_tts.py';
    $voiceName = getenv('AD_REEL_VOICE') ?: 'sr-RS-NicholasNeural';
    $code = adReelRun(
        [adReelPythonBinary(), $ttsPy, $scriptPath, $voice, $voiceName, '-12%'],
        $out,
        $err
    );
    if ($code !== 0 || !is_file($voice) || filesize($voice) < 1000) {
        return ['ok' => false, 'error' => 'tts_failed: ' . trim($err . ' ' . $out)];
    }

    $durOut = '';
    adReelRun([
        adReelFfprobeBinary(), '-v', 'error', '-show_entries', 'format=duration',
        '-of', 'default=noprint_wrappers=1:nokey=1', $voice,
    ], $durOut, $durErr);
    $duration = (float)trim($durOut);
    if ($duration < 2) {
        $duration = 12.0;
    }

    $fps = 30;
    $frames = max(60, (int)ceil($duration * $fps));
    $t1 = max(2.5, round($duration * 0.40, 3));
    $t2 = max(2.5, round($duration * 0.38, 3));
    $t3start = $t1 + $t2;

    // Ken Burns na fotki + providni UI overlayi sa smenom scena.
    $filter = sprintf(
        "[0:v]scale=1400:2489:force_original_aspect_ratio=increase,crop=1400:2489,"
        . "zoompan=z='min(1.0+0.00045*on,1.14)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=%d:s=1080x1920:fps=%d,"
        . "format=yuv420p[base];"
        . "[1:v]format=rgba[h];[2:v]format=rgba[f];[3:v]format=rgba[o];"
        . "[base][h]overlay=0:0:enable='lt(t,%.3f)'[v1];"
        . "[v1][f]overlay=0:0:enable='between(t,%.3f,%.3f)'[v2];"
        . "[v2][o]overlay=0:0:enable='gte(t,%.3f)'[v]",
        $frames,
        $fps,
        $t1,
        $t1,
        $t3start,
        $t3start
    );

    $code = adReelRun([
        adReelFfmpegBinary(), '-y',
        '-loop', '1', '-i', $baseJpg,
        '-i', $ovHero,
        '-i', $ovFacts,
        '-i', $ovOutro,
        '-i', $voice,
        '-filter_complex', $filter,
        '-map', '[v]',
        '-map', '4:a',
        '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '22',
        '-c:a', 'aac', '-b:a', '160k',
        '-t', number_format($duration, 3, '.', ''),
        '-movflags', '+faststart',
        $outFs,
    ], $vout, $verr);

    if ($code !== 0 || !is_file($outFs) || filesize($outFs) < 20_000) {
        return ['ok' => false, 'error' => 'ffmpeg_failed: ' . trim($verr . ' ' . $vout)];
    }

    file_put_contents($metaPath, "duration={$duration}; frames={$frames}; t1={$t1}; t2={$t2}\n");

    return [
        'ok' => true,
        'path' => $outFs,
        'public' => $outPub,
        'script' => $script,
        'duration' => $duration,
    ];
}

<?php

declare(strict_types=1);

/**
 * Auto reel (9:16) iz oglasa — frameovi + TTS + ffmpeg.
 * Prototip za FB/IG Reels stil (AutoPijac-like) sa govorom.
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

function adReelNarration(array $ad): string
{
    $title = trim(function_exists('adDisplayTitle') ? adDisplayTitle($ad) : (string)($ad['title'] ?? 'Oglas'));
    $price = function_exists('adCardPriceMainLabel') ? adCardPriceMainLabel($ad) : formatAdPrice($ad);
    $loc = trim((string)($ad['location'] ?? ''));
    $brand = trim((string)($ad['brand'] ?? ''));
    $model = trim((string)($ad['model'] ?? ''));
    $storage = trim((string)($ad['storage'] ?? ''));
    $condition = trim((string)($ad['condition_state'] ?? ''));
    $battery = isset($ad['battery_health']) && $ad['battery_health'] !== '' && $ad['battery_health'] !== null
        ? ((int)$ad['battery_health']) . ' posto'
        : '';
    $type = function_exists('getAdType') ? getAdType($ad) : (string)($ad['ad_type'] ?? 'telefon');

    $parts = [];
    if ($type === 'telefon') {
        $parts[] = 'Pogledaj ovaj telefon na Kupi Telefon.';
    } elseif ($type === 'delovi') {
        $parts[] = 'Pogledaj ovaj deo na Kupi Telefon.';
    } else {
        $parts[] = 'Pogledaj ovu uslugu na Kupi Telefon.';
    }

    $name = trim($brand . ' ' . $model);
    if ($name === '') {
        $name = $title;
    }
    $parts[] = $name . '.';

    $facts = [];
    if ($storage !== '') {
        $facts[] = 'memorija ' . $storage;
    }
    if ($battery !== '') {
        $facts[] = 'baterija ' . $battery;
    }
    if ($condition !== '') {
        $facts[] = 'stanje ' . mb_strtolower($condition);
    }
    if ($facts !== []) {
        $parts[] = 'Ima ' . implode(', ', $facts) . '.';
    }

    if ($price !== '' && $price !== 'Po dogovoru') {
        $parts[] = 'Cena je ' . preg_replace('/\s+/u', ' ', $price) . '.';
    } else {
        $parts[] = 'Cena po dogovoru.';
    }

    if ($loc !== '') {
        $parts[] = 'Lokacija: ' . $loc . '.';
    }

    $parts[] = 'Detalje i kontakt nadji na kupitelefon.rs.';

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

/**
 * @param 'hero'|'facts'|'outro' $variant
 */
function adReelRenderFrame(array $ad, string $photoPath, string $variant, string $destPng): bool
{
    $W = 1080;
    $H = 1920;
    $canvas = imagecreatetruecolor($W, $H);
    if ($canvas === false) {
        return false;
    }

    $bg = imagecolorallocate($canvas, 12, 16, 14);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    $green = imagecolorallocate($canvas, 45, 122, 62);
    $greenDark = imagecolorallocate($canvas, 28, 90, 44);
    $yellow = imagecolorallocate($canvas, 245, 197, 24);
    $muted = imagecolorallocate($canvas, 210, 214, 208);
    $priceC = imagecolorallocate($canvas, 245, 197, 24);
    $panel = imagecolorallocatealpha($canvas, 0, 0, 0, 60);
    imagealphablending($canvas, true);
    imagesavealpha($canvas, true);
    imagefilledrectangle($canvas, 0, 0, $W, $H, $bg);

    $src = adReelLoadImage($photoPath);
    $photoTop = 220;
    $photoH = 1180;
    if ($src instanceof \GdImage) {
        adReelPasteCover($canvas, $src, 0, $photoTop, $W, $photoH);
        imagedestroy($src);
        // dark gradient bars
        for ($i = 0; $i < 160; $i++) {
            $a = (int)round(90 * (1 - $i / 160));
            $c = imagecolorallocatealpha($canvas, 0, 0, 0, max(0, min(127, 127 - $a)));
            imageline($canvas, 0, $photoTop + $i, $W, $photoTop + $i, $c);
            imageline($canvas, 0, $photoTop + $photoH - 1 - $i, $W, $photoTop + $photoH - 1 - $i, $c);
        }
    }

    // top brand bar
    imagefilledrectangle($canvas, 0, 0, $W, 96, $green);
    imagefilledrectangle($canvas, 0, 96, $W, 104, $yellow);
    adReelDrawText($canvas, 'KupiTelefon.rs', 48, 64, 34, $white, true);

    $price = function_exists('adCardPriceMainLabel') ? adCardPriceMainLabel($ad) : formatAdPrice($ad);
    $loc = trim((string)($ad['location'] ?? ''));
    $title = trim(function_exists('adDisplayTitle') ? adDisplayTitle($ad) : (string)($ad['title'] ?? 'Oglas'));

    // price pill
    imagefilledrectangle($canvas, 40, 130, 1040, 200, $greenDark);
    adReelDrawText($canvas, 'CENA', 64, 178, 22, $yellow, true);
    adReelDrawText($canvas, $price !== '' ? $price : 'Po dogovoru', 180, 182, 36, $white, true);
    if ($loc !== '') {
        $lw = adReelTextWidth($loc, 24, true);
        adReelDrawText($canvas, $loc, 1040 - $lw - 40, 180, 24, $muted, true);
    }

    if ($variant === 'hero' || $variant === 'facts') {
        $y = $photoTop + $photoH + 40;
        $lines = adReelWrap($title, 36, 960, 3, true);
        foreach ($lines as $line) {
            adReelDrawText($canvas, $line, 60, $y, 36, $white, true);
            $y += 52;
        }

        $badges = [];
        foreach ([
            trim((string)($ad['storage'] ?? '')),
            (isset($ad['battery_health']) && $ad['battery_health'] !== '' && $ad['battery_health'] !== null)
                ? ((int)$ad['battery_health'] . '% BH') : '',
            trim((string)($ad['condition_state'] ?? '')),
            function_exists('getAdType') ? match (getAdType($ad)) {
                'telefon' => 'Telefon',
                'delovi' => 'Delovi',
                'servis' => 'Servis',
                default => '',
            } : '',
        ] as $b) {
            if ($b !== '') {
                $badges[] = $b;
            }
        }

        $bx = 60;
        $by = min($y + 28, 1780);
        foreach (array_slice($badges, 0, 4) as $badge) {
            $tw = adReelTextWidth($badge, 22, true) + 36;
            if ($bx + $tw > 1020) {
                $bx = 60;
                $by += 58;
            }
            imagefilledrectangle($canvas, $bx, $by - 34, $bx + $tw, $by + 10, $green);
            adReelDrawText($canvas, $badge, $bx + 18, $by, 22, $white, true);
            $bx += $tw + 14;
        }

        if ($variant === 'facts') {
            adReelDrawText($canvas, 'Detalji i kontakt na sajtu', 60, 1860, 26, $yellow, true);
        }
    }

    if ($variant === 'outro') {
        imagefilledrectangle($canvas, 0, 700, $W, 1300, $green);
        imagefilledrectangle($canvas, 0, 1300, $W, 1310, $yellow);
        adReelDrawText($canvas, 'KupiTelefon.rs', 200, 900, 52, $white, true);
        adReelDrawText($canvas, 'Telefoni · Delovi · Servis', 210, 980, 30, $yellow, true);
        $cta = 'Otvori oglas i javi se';
        $cw = adReelTextWidth($cta, 34, true);
        adReelDrawText($canvas, $cta, (int)(($W - $cw) / 2), 1120, 34, $white, true);
        if ($price !== '') {
            adReelDrawText($canvas, $price, (int)(($W - adReelTextWidth($price, 42, true)) / 2), 1220, 42, $yellow, true);
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
    $frameHero = $dir . '/frame_hero.png';
    $frameFacts = $dir . '/frame_facts.png';
    $frameOutro = $dir . '/frame_outro.png';
    $voice = $dir . '/voice.mp3';
    $scriptPath = $dir . '/script.txt';
    $concat = $dir . '/concat.txt';

    if (!adReelRenderFrame($ad, $photo, 'hero', $frameHero)) {
        return ['ok' => false, 'error' => 'frame_hero_failed'];
    }
    if (!adReelRenderFrame($ad, $photo, 'facts', $frameFacts)) {
        return ['ok' => false, 'error' => 'frame_facts_failed'];
    }
    if (!adReelRenderFrame($ad, $photo, 'outro', $frameOutro)) {
        return ['ok' => false, 'error' => 'frame_outro_failed'];
    }

    $script = adReelNarration($ad);
    file_put_contents($scriptPath, $script);

    $ttsPy = dirname(__DIR__) . '/tools/ad_reel_tts.py';
    $voiceName = getenv('AD_REEL_VOICE') ?: 'sr-RS-SophieNeural';
    $code = adReelRun(
        [adReelPythonBinary(), $ttsPy, $scriptPath, $voice, $voiceName],
        $out,
        $err
    );
    if ($code !== 0 || !is_file($voice) || filesize($voice) < 1000) {
        return ['ok' => false, 'error' => 'tts_failed: ' . trim($err . ' ' . $out)];
    }

    // Probe duration
    $ffprobe = str_replace('ffmpeg', 'ffprobe', adReelFfmpegBinary());
    if (str_ends_with(adReelFfmpegBinary(), 'ffmpeg.exe')) {
        $ffprobe = preg_replace('/ffmpeg\.exe$/i', 'ffprobe.exe', adReelFfmpegBinary()) ?: $ffprobe;
    }
    $durOut = '';
    adReelRun([
        $ffprobe, '-v', 'error', '-show_entries', 'format=duration',
        '-of', 'default=noprint_wrappers=1:nokey=1', $voice,
    ], $durOut, $durErr);
    $duration = (float)trim($durOut);
    if ($duration < 2) {
        $duration = 12.0;
    }

    // Trajanje scena = trajanje TTS (hero / facts / outro)
    $t1 = max(2.5, round($duration * 0.42, 3));
    $t2 = max(2.5, round($duration * 0.38, 3));
    $t3 = max(2.0, round($duration - $t1 - $t2, 3));

    // Jedan ffmpeg prolaz: 3 scene + TTS audio.
    $filter = '[0:v]scale=1080:1920,setsar=1,fps=30,format=yuv420p[v0];'
        . '[1:v]scale=1080:1920,setsar=1,fps=30,format=yuv420p[v1];'
        . '[2:v]scale=1080:1920,setsar=1,fps=30,format=yuv420p[v2];'
        . '[v0][v1][v2]concat=n=3:v=1:a=0[v]';
    $code = adReelRun([
        adReelFfmpegBinary(), '-y',
        '-loop', '1', '-t', number_format($t1, 3, '.', ''), '-i', $frameHero,
        '-loop', '1', '-t', number_format($t2, 3, '.', ''), '-i', $frameFacts,
        '-loop', '1', '-t', number_format($t3, 3, '.', ''), '-i', $frameOutro,
        '-i', $voice,
        '-filter_complex', $filter,
        '-map', '[v]',
        '-map', '3:a',
        '-c:v', 'libx264', '-preset', 'ultrafast', '-crf', '26',
        '-c:a', 'aac', '-b:a', '128k',
        '-shortest',
        '-movflags', '+faststart',
        $outFs,
    ], $vout, $verr);
    if ($code !== 0 || !is_file($outFs) || filesize($outFs) < 20_000) {
        return ['ok' => false, 'error' => 'ffmpeg_failed: ' . trim($verr . ' ' . $vout)];
    }

    // Keep concat path for debug only
    file_put_contents($concat, "t1={$t1}; t2={$t2}; t3={$t3}; duration={$duration}\n");

    return [
        'ok' => true,
        'path' => $outFs,
        'public' => $outPub,
        'script' => $script,
        'duration' => $duration,
    ];
}

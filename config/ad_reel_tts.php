<?php

declare(strict_types=1);

/**
 * Pro TTS za reel voiceover.
 * Prioritet: ElevenLabs (AutoPijac-like) → Azure Speech → Edge (samo fallback).
 */

function adReelTtsProvider(): string
{
    $forced = strtolower(trim((string)envValue('AD_REEL_TTS_PROVIDER', '')));
    if (in_array($forced, ['elevenlabs', 'azure', 'edge'], true)) {
        return $forced;
    }
    if (trim((string)envValue('ELEVENLABS_API_KEY', '')) !== '') {
        return 'elevenlabs';
    }
    if (trim((string)envValue('AZURE_SPEECH_KEY', '')) !== '') {
        return 'azure';
    }
    return 'edge';
}

/**
 * @return array{ok:bool,provider?:string,error?:string,bytes?:int}
 */
function adReelSynthesizeVoice(string $script, string $outMp3): array
{
    $script = trim($script);
    if ($script === '') {
        return ['ok' => false, 'error' => 'empty_script'];
    }
    $dir = dirname($outMp3);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $provider = adReelTtsProvider();
    return match ($provider) {
        'elevenlabs' => adReelTtsElevenLabs($script, $outMp3),
        'azure' => adReelTtsAzure($script, $outMp3),
        default => adReelTtsEdge($script, $outMp3),
    };
}

/**
 * ElevenLabs — glas Ivan (Deep Serbian/Croatian Balkan).
 * Model: eleven_multilingual_v2
 *
 * @return array{ok:bool,provider?:string,error?:string,bytes?:int}
 */
function adReelTtsElevenLabs(string $script, string $outMp3): array
{
    $apiKey = trim((string)envValue('ELEVENLABS_API_KEY', ''));
    if ($apiKey === '') {
        return ['ok' => false, 'provider' => 'elevenlabs', 'error' => 'missing_ELEVENLABS_API_KEY'];
    }

    // Ivan — Deep Serbian & Croatian (Balkan)
    $voiceId = trim((string)envValue('ELEVENLABS_VOICE_ID', 'j96cp162VcYdsYfSp1nc'));
    $py = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
    $envPy = trim((string)(getenv('PYTHON_PATH') ?: ''));
    if ($envPy !== '') {
        $py = $envPy;
    }
    $ttsPy = dirname(__DIR__) . '/tools/ad_reel_tts_eleven.py';
    $scriptPath = $outMp3 . '.txt';
    file_put_contents($scriptPath, $script);

    // Put settings into env for the Python helper.
    // Jasnije + ubedljivije: malo više stability, izraženiji style.
    putenv('ELEVENLABS_MODEL_ID=' . trim((string)envValue('ELEVENLABS_MODEL_ID', 'eleven_multilingual_v2')));
    putenv('ELEVENLABS_STABILITY=' . trim((string)envValue('ELEVENLABS_STABILITY', '0.48')));
    putenv('ELEVENLABS_SIMILARITY=' . trim((string)envValue('ELEVENLABS_SIMILARITY', '0.80')));
    putenv('ELEVENLABS_STYLE=' . trim((string)envValue('ELEVENLABS_STYLE', '0.62')));

    $tmp = sys_get_temp_dir() . '/kt_el_' . bin2hex(random_bytes(4));
    $outFile = $tmp . '.out';
    $errFile = $tmp . '.err';
    $cmd = [$py, $ttsPy, $scriptPath, $outMp3, $voiceId, $apiKey];
    $proc = proc_open($cmd, [
        0 => ['pipe', 'r'],
        1 => ['file', $outFile, 'w'],
        2 => ['file', $errFile, 'w'],
    ], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proc)) {
        @unlink($scriptPath);
        return ['ok' => false, 'provider' => 'elevenlabs', 'error' => 'proc_open_failed'];
    }
    fclose($pipes[0]);
    $code = proc_close($proc);
    $err = is_file($errFile) ? (string)file_get_contents($errFile) : '';
    $out = is_file($outFile) ? (string)file_get_contents($outFile) : '';
    @unlink($outFile);
    @unlink($errFile);
    @unlink($scriptPath);

    if ($code !== 0 || !is_file($outMp3) || filesize($outMp3) < 1000) {
        return ['ok' => false, 'provider' => 'elevenlabs', 'error' => trim($err . ' ' . $out)];
    }
    return ['ok' => true, 'provider' => 'elevenlabs', 'bytes' => (int)filesize($outMp3)];
}

/**
 * Azure Neural TTS (sr-RS) — bolji od free Edge scrapera, SSML + 48kHz.
 *
 * @return array{ok:bool,provider?:string,error?:string,bytes?:int}
 */
function adReelTtsAzure(string $script, string $outMp3): array
{
    $key = trim((string)envValue('AZURE_SPEECH_KEY', ''));
    $region = trim((string)envValue('AZURE_SPEECH_REGION', 'westeurope'));
    if ($key === '') {
        return ['ok' => false, 'provider' => 'azure', 'error' => 'missing_AZURE_SPEECH_KEY'];
    }

    $voice = trim((string)envValue('AZURE_SPEECH_VOICE', 'sr-RS-NicholasNeural'));
    $escaped = htmlspecialchars($script, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $ssml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<speak version="1.0" xml:lang="sr-RS">'
        . '<voice name="' . htmlspecialchars($voice, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '">'
        . '<prosody rate="-8%" pitch="-1%">' . $escaped . '</prosody>'
        . '</voice></speak>';

    $url = 'https://' . $region . '.tts.speech.microsoft.com/cognitiveservices/v1';
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'provider' => 'azure', 'error' => 'curl_init_failed'];
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Ocp-Apim-Subscription-Key: ' . $key,
            'Content-Type: application/ssml+xml',
            'X-Microsoft-OutputFormat: audio-24khz-48kbitrate-mono-mp3',
            'User-Agent: KupiTelefonReel',
        ],
        CURLOPT_POSTFIELDS => $ssml,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code < 200 || $code >= 300) {
        $hint = is_string($body) ? mb_substr($body, 0, 300) : $err;
        return ['ok' => false, 'provider' => 'azure', 'error' => 'http_' . $code . ': ' . $hint];
    }
    file_put_contents($outMp3, $body);
    return ['ok' => true, 'provider' => 'azure', 'bytes' => strlen($body)];
}

/**
 * Edge fallback (nije pro kvalitet — samo ako nema API ključa).
 *
 * @return array{ok:bool,provider?:string,error?:string,bytes?:int}
 */
function adReelTtsEdge(string $script, string $outMp3): array
{
    $py = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
    $envPy = trim((string)(getenv('PYTHON_PATH') ?: ''));
    if ($envPy !== '') {
        $py = $envPy;
    }
    $ttsPy = dirname(__DIR__) . '/tools/ad_reel_tts.py';
    $scriptPath = $outMp3 . '.txt';
    file_put_contents($scriptPath, $script);
    $voice = getenv('AD_REEL_VOICE') ?: 'sr-RS-NicholasNeural';

    $tmp = sys_get_temp_dir() . '/kt_edge_' . bin2hex(random_bytes(4));
    $outFile = $tmp . '.out';
    $errFile = $tmp . '.err';
    $cmd = [$py, $ttsPy, $scriptPath, $outMp3, $voice, '-10%'];
    $proc = proc_open($cmd, [
        0 => ['pipe', 'r'],
        1 => ['file', $outFile, 'w'],
        2 => ['file', $errFile, 'w'],
    ], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proc)) {
        @unlink($scriptPath);
        return ['ok' => false, 'provider' => 'edge', 'error' => 'proc_open_failed'];
    }
    fclose($pipes[0]);
    $code = proc_close($proc);
    $err = is_file($errFile) ? (string)file_get_contents($errFile) : '';
    $out = is_file($outFile) ? (string)file_get_contents($outFile) : '';
    @unlink($outFile);
    @unlink($errFile);
    @unlink($scriptPath);

    if ($code !== 0 || !is_file($outMp3) || filesize($outMp3) < 1000) {
        return ['ok' => false, 'provider' => 'edge', 'error' => trim($err . ' ' . $out)];
    }
    return ['ok' => true, 'provider' => 'edge', 'bytes' => (int)filesize($outMp3)];
}

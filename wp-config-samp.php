<?php
// wp-backdoor-fullscan-ultimate.php
// FULL AUTO DELETE BACKDOOR SCANNER
// wp-confip.php = ABSOLUTE OWNER FILE (LOCKED)

//////////////// CONFIG //////////////////
$BROWSER_KEY     = 'RAHASIASENPAI';
$EXTS            = ['php','php5','php7','phtml','inc','tpl'];
$WHITELIST_FILES = ['wp-confip.php'];
$EXCLUDE_DIRS    = ['/cache/','/uploads/','/node_modules/','/vendor/'];
$MAX_FILE_SIZE   = 5 * 1024 * 1024;
$SCORE_THRESHOLD = 1;      // MODE AGRESIF
$AUTO_DELETE     = true;   // AUTO DELETE AKTIF
/////////////////////////////////////////

ini_set('memory_limit','1536M');
set_time_limit(0);

/* ===== Browser Auth ===== */
if (php_sapi_name() !== 'cli') {
    if (!function_exists('hash_equals') ||
        !hash_equals($BROWSER_KEY, $_GET['key'] ?? '')
    ) {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
}

/* ===== Find WP Root ===== */
function find_wp_root($start) {
    $p = realpath($start);
    for ($i=0; $i<50; $i++) {
        if ($p && file_exists($p.'/wp-config.php')) return $p;
        $parent = dirname($p);
        if ($parent === $p) break;
        $p = $parent;
    }
    return false;
}

$WP_ROOT = find_wp_root(__DIR__);
if (!$WP_ROOT) exit("WP root not found\n");

/* ===== MALWARE SIGNATURES ===== */
$PATTERNS = [

    // OBFUSCATION
    'eval'              => '/\beval\s*\(/i',
    'base64_decode'     => '/base64_decode\s*\(/i',
    'gzinflate'         => '/gzinflate\s*\(/i',
    'gzuncompress'      => '/gzuncompress\s*\(/i',
    'str_rot13'         => '/str_rot13\s*\(/i',
    'convert_uu'        => '/convert_uudecode\s*\(/i',

    // ADVANCED
    'chr_chain'         => '/chr\s*\(\s*\d+\s*\)/i',
    'hex_string'        => '/0x[0-9a-f]{2,}/i',
    'escaped_hex'       => '/(\\\\x[0-9A-Fa-f]{2}){5,}/',

    // DYNAMIC EXEC
    'variable_func'     => '/\$\w+\s*\(/',
    'variable_variable' => '/\$\$\w+/',
    'call_user_func'    => '/call_user_func(_array)?\s*\(/i',

    // FLOW ABUSE
    'goto_usage'        => '/\bgoto\s+[a-zA-Z0-9_]+;/i',

    // COMMAND EXEC
    'shell_exec'        => '/shell_exec\s*\(/i',
    'system'            => '/\bsystem\s*\(/i',
    'exec'              => '/\bexec\s*\(/i',
    'passthru'          => '/passthru\s*\(/i',
    'popen'             => '/popen\s*\(/i',
    'proc_open'         => '/proc_open\s*\(/i',

    // FILE DROP
    'file_put_contents' => '/file_put_contents\s*\(/i',
    'fwrite'            => '/fwrite\s*\(/i',

    // REMOTE PAYLOAD
    'curl_exec'         => '/curl_exec\s*\(/i',
    'fsockopen'         => '/fsockopen\s*\(/i',
    'remote_include'    => '/(include|require).*https?:\/\//i',
    'remote_get'        => '/file_get_contents\s*\(\s*[\'"]https?:\/\//i',

    // LEGACY EVIL
    'assert'            => '/\bassert\s*\(/i',
    'preg_replace_e'    => '/preg_replace\s*\(.*\/e[\'"]/i',
    'create_function'   => '/\bcreate_function\s*\(/i',
];

/* ===== KILL ON SIGHT ===== */
$KILL_ON_SIGHT = [
    'eval','goto_usage','call_user_func',
    'variable_func','shell_exec','proc_open'
];

$NAME_BAD = '/(shell|wso|r57|c99|b374k|cmd|mini|webshell|phpinfo|adminer)/i';

header('Content-Type: text/plain; charset=utf-8');

$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($WP_ROOT, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($rii as $f) {
    if (!$f->isFile()) continue;

    $real = $f->getRealPath();
    if (!$real) continue;

    $name = $f->getFilename();

    // HARD WHITELIST
    if (in_array($name, $WHITELIST_FILES, true)) continue;

    // EXCLUDE DIR
    foreach ($EXCLUDE_DIRS as $ex) {
        if (strpos($real, $WP_ROOT.$ex) === 0) continue 2;
    }

    if (!in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $EXTS)) continue;
    if (@filesize($real) > $MAX_FILE_SIZE) continue;

    $c = @file_get_contents($real);
    if ($c === false) continue;

    $score = 0;
    $why   = [];

    foreach ($PATTERNS as $label => $rx) {
        if (preg_match($rx, $c)) {
            $score++;
            $why[] = $label;
        }
    }

    // MINI SHELL (1–3 BARIS)
    if (
        preg_match('/\$_(GET|POST|REQUEST|COOKIE)\s*\[/', $c) &&
        preg_match('/(eval|assert|system|exec|shell_exec|call_user_func)/i', $c)
    ) {
        $score += 3;
        $why[] = 'mini_shell';
    }

    if (preg_match($NAME_BAD, $name)) {
        $score++;
        $why[] = 'bad_filename';
    }

    // KILL ON SIGHT
    foreach ($KILL_ON_SIGHT as $k) {
        if (in_array($k, $why, true)) {
            @unlink($real);
            echo "[KILLED] {$real} ({$k})\n";
            continue 2;
        }
    }

    if ($score >= $SCORE_THRESHOLD) {
        echo "[{$score}] {$real}\n";
        foreach ($why as $w) echo "  - {$w}\n";

        if ($AUTO_DELETE) {
            @unlink($real)
                ? print("  ✔ DELETED\n\n")
                : print("  ✖ FAILED\n\n");
        }
    }
}

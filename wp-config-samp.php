<?php
// wp-backdoor-fullscan-ultimate.php
// READ-ONLY forensic backdoor scanner
// wp-confip.php = OWNER FILE (ABSOLUTELY SKIPPED)

//////////////// CONFIG //////////////////
$BROWSER_KEY     = 'RAHASIASENPAI';
$EXTS            = ['php','php5','php7','phtml','inc','tpl'];
$WHITELIST_FILES = ['wp-confip.php'];               // NEVER touched
$EXCLUDE_DIRS    = ['/cache/','/uploads/','/node_modules/','/vendor/'];
$MAX_FILE_SIZE   = 5 * 1024 * 1024;             // 5MB
$SCORE_THRESHOLD = 2;
/////////////////////////////////////////

ini_set('memory_limit','1536M');
set_time_limit(0);

/* ===== Browser auth ===== */
if (php_sapi_name() !== 'cli') {
    if (!function_exists('hash_equals') ||
        !hash_equals($BROWSER_KEY, $_GET['key'] ?? '')
    ) {
        header('HTTP/1.1 403 Forbidden'); exit;
    }
}

/* ===== Find WP root ===== */
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

/* ===== Patterns (labeled) ===== */
$PATTERNS = [
    'eval_base64'        => '/eval\s*\(\s*base64_decode\s*\(/i',
    'eval_gzinflate'     => '/eval\s*\(\s*gzinflate\s*\(\s*base64_decode\s*\(/i',
    'gzinflate_base64'   => '/gzinflate\s*\(\s*base64_decode\s*\(/i',
    'base64_decode'      => '/base64_decode\s*\(/i',
    'gzinflate'          => '/gzinflate\s*\(/i',
    'gzuncompress'       => '/gzuncompress\s*\(/i',
    'rot13'              => '/str_rot13\s*\(/i',

    'exec_func'          => '/\b(shell_exec|exec|system|passthru|popen|proc_open)\s*\(/i',
    'assert'             => '/\bassert\s*\(/i',
    'create_function'    => '/\bcreate_function\s*\(/i',
    'file_put_contents'  => '/file_put_contents\s*\(/i',
    'curl_exec'          => '/curl_exec\s*\(/i',
    'fsockopen'          => '/fsockopen\s*\(/i',

    'remote_include'     => '/(include|require|include_once|require_once)[^\n;]*https?:\/\//i',
    'remote_fopen'       => '/fopen\s*\(\s*[\'"]https?:\/\//i',
    'remote_getcontents' => '/file_get_contents\s*\(\s*[\'"]https?:\/\//i',

    'preg_replace_e'     => '/preg_replace\s*\(\s*[\'"].+\/e[\'"]/i',
    'hex_obfuscation'    => '/(\\\\x[0-9A-Fa-f]{2}){6,}/',
];

$NAME_BAD = '/(shell|wso|r57|c99|b374k|backdoor|webshell|cmd|mini)/i';

header('Content-Type: text/plain; charset=utf-8');

$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($WP_ROOT, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($rii as $f) {
    if (!$f->isFile()) continue;

    $real = $f->getRealPath();
    if (!$real) continue;

    $name = $f->getFilename();

    /* ===== ABSOLUTE OWNER SAFE ===== */
    if (in_array($name, $WHITELIST_FILES, true)) continue;

    /* ===== Excluded dirs ===== */
    foreach ($EXCLUDE_DIRS as $ex) {
        if (strpos($real, $WP_ROOT.$ex) === 0) continue 2;
    }

    /* ===== Extension ===== */
    if (!in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $EXTS, true)) continue;

    /* ===== Size guard ===== */
    if (@filesize($real) > $MAX_FILE_SIZE) continue;

    $c = @file_get_contents($real);
    if ($c === false) continue;

    $score = 0;
    $why   = [];

    /* ===== Pattern scan ===== */
    foreach ($PATTERNS as $label => $rx) {
        if (preg_match($rx, $c)) {
            $score++;
            $why[] = $label;
        }
    }

    /* ===== Filename heuristic ===== */
    if (preg_match($NAME_BAD, $name)) {
        $score++;
        $why[] = 'suspicious_filename';
    }

    /* ===== Entropy / symbol-heavy ===== */
    $len = strlen($c);
    if ($len > 200) {
        $non = preg_match_all('/[^a-zA-Z0-9\s]/', $c);
        if ($non && ($non / $len) > 0.30) {
            $score++;
            $why[] = 'high_symbol_ratio';
        }
    }

    /* ===== Result ===== */
    if ($score >= $SCORE_THRESHOLD) {
        $rel = substr($real, strlen($WP_ROOT));
        echo "[{$score}] {$rel}\n";
        foreach ($why as $w) {
            echo "    - {$w}\n";
        }
        echo "\n";
    }
}

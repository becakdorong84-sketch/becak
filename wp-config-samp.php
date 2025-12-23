<?php
// wp-backdoor-safescan.php
// SAFE WordPress backdoor scanner (read-only, shared-hosting friendly)

//////////////// CONFIG //////////////////
$BROWSER_KEY = 'RAHASIASENPAI';

$SCAN_DIRS = [
    'wp-content/uploads',
    'wp-content/cache',
    'wp-includes'
];

$EXTS = ['php','phtml','inc'];

$MAX_FILE_SIZE = 40000;      // max 40 KB (shell biasanya kecil)
$MIN_FILE_SIZE = 200;        // skip file terlalu kecil
$MAX_READ      = 65536;      // baca max 64 KB saja
$MAX_FILES     = 20000;      // HARD LIMIT
$SLEEP_EVERY   = 300;
$SLEEP_US      = 30000;      // 30ms

$PATTERNS = [
    'eval(',
    'base64_decode(',
    'gzinflate(',
    'gzuncompress(',
    'str_rot13(',
    'shell_exec',
    'system(',
    'passthru(',
    'exec(',
    'assert(',
    'file_put_contents',
    'curl_exec',
    'fsockopen',
];

/////////////////////////////////////////

ini_set('memory_limit','256M');
set_time_limit(0);

// Browser key
if (php_sapi_name() !== 'cli') {
    $k = $_GET['key'] ?? '';
    if (!hash_equals($BROWSER_KEY, $k)) {
        header('HTTP/1.1 403 Forbidden');
        exit('403 Forbidden');
    }
}

// find wp root
function wp_root($d){
    for($i=0;$i<10;$i++){
        if(file_exists($d.'/native.php')) return realpath($d);
        $p = dirname($d);
        if($p===$d) break;
        $d = $p;
    }
    return false;
}

$ROOT = wp_root(__DIR__);
if(!$ROOT) die('WP root not found');

$FOUND = [];
$SCANNED = 0;

foreach ($SCAN_DIRS as $dir) {
    $path = $ROOT . '/' . $dir;
    if (!is_dir($path)) continue;

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        if (++$SCANNED > $MAX_FILES) break 2;

        $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, $EXTS)) continue;

        $size = $f->getSize();
        if ($size < $MIN_FILE_SIZE || $size > $MAX_FILE_SIZE) continue;

        $fp = @fopen($f->getPathname(), 'rb');
        if (!$fp) continue;

        $data = fread($fp, $MAX_READ);
        fclose($fp);

        $hit = 0;
        foreach ($PATTERNS as $p) {
            if (stripos($data, $p) !== false) {
                $hit++;
            }
        }

        // base64 blob 1-line
        if (
            strlen($data) < 8000 &&
            preg_match('/^[A-Za-z0-9+\/=\s]+$/', trim($data)) &&
            substr(trim($data), -1) === '='
        ) {
            $hit += 2;
        }

        if ($hit >= 1) {
            $rel = str_replace($ROOT, '', $f->getPathname());
            $FOUND[] = $rel;
        }

        if (($SCANNED % $SLEEP_EVERY) === 0) usleep($SLEEP_US);
    }
}

sort($FOUND);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>WP Safe Backdoor Scan</title>
<style>
body{font-family:system-ui;background:#f5f7fb;padding:20px}
pre{background:#0b1220;color:#dff5ff;padding:12px;border-radius:6px;max-height:70vh;overflow:auto}
button{padding:8px 12px;border:0;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer}
.note{color:#555;font-size:13px;margin-top:10px}
</style>
</head>
<body>

<h3>WP Backdoor Scanner — SAFE MODE</h3>
<div>WP Root: <b><?=htmlspecialchars($ROOT)?></b></div>
<div>Found: <b><?=count($FOUND)?></b></div>

<br>
<button onclick="copy()">Copy All</button>

<?php if(!$FOUND): ?>
<p class="note">No suspicious files found.</p>
<pre id="r"></pre>
<?php else: ?>
<pre id="r"><?=htmlspecialchars(implode("\n",$FOUND))?></pre>
<?php endif; ?>

<p class="note">
✔ Read-only • ✔ Shared hosting safe • ✔ Limited scan<br>
Remove this file after use.
</p>

<script>
function copy(){
  let t=document.getElementById('r').textContent;
  if(!t) return alert('No data');
  navigator.clipboard.writeText(t);
  alert('Copied');
}
</script>

</body>
</html>

<?php
// generate.php
// POST endpoint that returns a 7-char code or an ERROR: message
// Stores codes in raffle_codes.txt as CSV lines: code,ip,timestamp

define('FILE_TICKETS', __DIR__ . '/raffle_codes.txt'); // make sure writable
define('COOKIE_NAME', 'raffle_ticket');
define('COOKIE_EXPIRE', time() + (86400 * 365)); // 1 year

// Only accept POST to avoid prefetch issues.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "ERROR:Invalid request method";
    exit;
}

// helper to get client IP (best-effort; adjust if behind proxy)
function client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // X-Forwarded-For may contain a list
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$ip = client_ip();

// If cookie exists, return existing ticket
if (!empty($_COOKIE[COOKIE_NAME])) {
    echo htmlspecialchars($_COOKIE[COOKIE_NAME], ENT_QUOTES, 'UTF-8');
    exit;
}

// Ensure file exists
if (!file_exists(FILE_TICKETS)) {
    // try to create the file
    file_put_contents(FILE_TICKETS, "");
    chmod(FILE_TICKETS, 0666); // optional: set world-writable if necessary
}

// Read existing tickets
$lines = file(FILE_TICKETS, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$tickets = [];
foreach ($lines as $line) {
    // expected: code,ip,timestamp
    $parts = str_getcsv($line);
    if (count($parts) >= 3) {
        $tickets[] = ['code' => $parts[0], 'ip' => $parts[1], 'ts' => $parts[2]];
    }
}

// IP-based prevention: if any ticket has same IP, return that code
foreach ($tickets as $t) {
    if ($t['ip'] === $ip) {
        // set cookie for convenience and return existing code
        setcookie(COOKIE_NAME, $t['code'], COOKIE_EXPIRE, '/');
        echo $t['code'];
        exit;
    }
}

// generate unique 7-char code (A-Z0-9)
function make_code($length = 7) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $max = strlen($chars) - 1;
    $c = '';
    for ($i = 0; $i < $length; $i++) {
        $c .= $chars[random_int(0, $max)];
    }
    return $c;
}

// ensure uniqueness (try a few times)
$existing_codes = array_column($tickets, 'code');
$code = '';
$tries = 0;
do {
    $code = make_code(7);
    $tries++;
    if ($tries > 50) {
        echo "ERROR:Could not generate unique code";
        exit;
    }
} while (in_array($code, $existing_codes));

// Append to file safely
$line = implode(',', [$code, $ip, time()]) . PHP_EOL;
$fp = fopen(FILE_TICKETS, 'a');
if (!$fp) {
    echo "ERROR:Could not open ticket file for writing";
    exit;
}
if (flock($fp, LOCK_EX)) {
    fwrite($fp, $line);
    fflush($fp);
    flock($fp, LOCK_UN);
} else {
    fclose($fp);
    echo "ERROR:Could not acquire file lock";
    exit;
}
fclose($fp);

// Set cookie for the user
setcookie(COOKIE_NAME, $code, COOKIE_EXPIRE, '/');

// Return the code
echo $code;
exit;

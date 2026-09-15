<?php

/**
 * Vendor-side unlock code generator. Run this on YOUR machine only —
 * it needs the private key, which must never be committed or deployed
 * to a client install (see licensing-authority/, .gitignore'd, and
 * app/config/licensing.php for the public half that ships instead).
 *
 * Usage:
 *   php tools/generate-license-code.php --install=<install_id> --expires=<date> [--key=<path>]
 *
 * Examples:
 *   php tools/generate-license-code.php --install=a1b2c3d4e5f67890 --expires=2027-03-15
 *   php tools/generate-license-code.php --install=a1b2c3d4e5f67890 --expires="2027-03-15 23:59:59"
 *
 * --install is the client's Installation ID, shown on their /license
 *           page — get it from them when they ask to renew.
 * --expires accepts any format strtotime() understands. A bare date
 *           (no time) is treated as end-of-day, so the client stays
 *           unlocked through the whole of that day.
 * --key     defaults to licensing-authority/private-key.pem, relative
 *           to the repo root. Override if you keep it elsewhere.
 *
 * Paste the printed code into an email/WhatsApp/SMS to the client —
 * they enter it on their Settings → Subscription page.
 */

define('APP_START', true);
define('APP_PATH', __DIR__ . '/../app');

// Must match app/config/app.php's date_default_timezone_set() exactly —
// this tool runs on YOUR machine, which may be in a different timezone
// (and one with DST, unlike Blantyre). Without this, a date typed here
// can land on a different calendar day once the app displays it, right
// around midnight — parse and display have to agree on "now".
date_default_timezone_set('Africa/Blantyre');

require APP_PATH . '/helpers/LicenseCode.php';

function fail(string $message): void
{
    fwrite(STDERR, "Error: $message\n");
    exit(1);
}

$options = getopt('', ['install:', 'expires:', 'key::']);

$installId  = $options['install'] ?? null;
$expiresRaw = $options['expires'] ?? null;
$keyPath    = $options['key'] ?? __DIR__ . '/../licensing-authority/private-key.pem';

if (!$installId || !preg_match('/^[0-9a-f]{16}$/i', $installId)) {
    fail('--install is required and must be the 16-character Installation ID from the client\'s /license page.');
}

if (!$expiresRaw) {
    fail('--expires is required, e.g. --expires=2027-03-15');
}

$hasTime = (bool) preg_match('/\d{1,2}:\d{2}/', $expiresRaw);
$expiresTimestamp = strtotime($hasTime ? $expiresRaw : $expiresRaw . ' 23:59:59');
if ($expiresTimestamp === false) {
    fail("Could not parse --expires value: $expiresRaw");
}
if ($expiresTimestamp <= time()) {
    fail('That expiry date is in the past — the app would reject this code immediately.');
}

if (!is_readable($keyPath)) {
    fail("Private key not found or not readable at: $keyPath\n"
        . "Regenerate it if it's genuinely missing, but note: doing so invalidates every code issued under the old key.");
}
$privateKeyPem = file_get_contents($keyPath);

try {
    $code = LicenseCode::generate(strtolower($installId), $expiresTimestamp, $privateKeyPem);
} catch (Throwable $e) {
    fail($e->getMessage());
}

echo "Install ID:  " . strtolower($installId) . "\n";
echo "Expires:     " . date('Y-m-d H:i:s', $expiresTimestamp) . "\n";
echo "Code:\n\n$code\n\n";

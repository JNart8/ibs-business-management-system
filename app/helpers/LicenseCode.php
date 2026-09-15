<?php

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

/**
 * Encodes, signs, and verifies subscription unlock codes.
 *
 * A code is: PREFIX + base64url( 8-byte install id + 4-byte expiry
 * timestamp + 1-byte signature length + EC signature ).
 *
 * The signature is ECDSA (prime256v1/SHA-256) over the 12-byte payload
 * (install id + expiry). Verifying only needs the PUBLIC key, which is
 * what ships in app/config/licensing.php — this class is safe to deploy
 * to every client install. Generating a code needs the PRIVATE key,
 * which never ships (see tools/generate-license-code.php).
 *
 * A client with full access to their own deployed PHP source can read
 * this class, but that only tells them the format — without the private
 * key they cannot produce a signature that verify() will accept.
 */
class LicenseCode
{
    const PREFIX = 'IBS2-';

    /**
     * Vendor-side: build and sign a code. Requires the PEM private key.
     */
    public static function generate(string $installId, int $expiresAtTimestamp, string $privateKeyPem): string
    {
        $payload = self::packPayload($installId, $expiresAtTimestamp);

        $pkey = openssl_pkey_get_private($privateKeyPem);
        if ($pkey === false) {
            throw new RuntimeException('Invalid private key: ' . openssl_error_string());
        }

        if (!openssl_sign($payload, $signature, $pkey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Signing failed: ' . openssl_error_string());
        }

        $blob = $payload . chr(strlen($signature)) . $signature;

        return self::PREFIX . self::base64UrlEncode($blob);
    }

    /**
     * App-side: verify a submitted code against the PEM public key.
     * Returns ['install_id' => string, 'expires_at' => int] on success,
     * or null if the code is malformed, tampered with, or not signed
     * by the matching private key.
     */
    public static function verify(string $code, string $publicKeyPem): ?array
    {
        $code = trim($code);
        if (strpos($code, self::PREFIX) !== 0) {
            return null;
        }

        $blob = self::base64UrlDecode(substr($code, strlen(self::PREFIX)));
        if ($blob === false || strlen($blob) < 13) {
            return null;
        }

        $payload   = substr($blob, 0, 12);
        $sigLength = ord($blob[12]);
        $signature = substr($blob, 13, $sigLength);
        if (strlen($signature) !== $sigLength) {
            return null;
        }

        $pubkey = openssl_pkey_get_public($publicKeyPem);
        if ($pubkey === false) {
            return null;
        }

        if (openssl_verify($payload, $signature, $pubkey, OPENSSL_ALGO_SHA256) !== 1) {
            return null;
        }

        [$installId, $expiresAtTimestamp] = self::unpackPayload($payload);

        return ['install_id' => $installId, 'expires_at' => $expiresAtTimestamp];
    }

    private static function packPayload(string $installId, int $expiresAtTimestamp): string
    {
        return hex2bin($installId) . pack('N', $expiresAtTimestamp);
    }

    private static function unpackPayload(string $payload): array
    {
        $installId          = bin2hex(substr($payload, 0, 8));
        $expiresAtTimestamp = unpack('N', substr($payload, 8, 4))[1];

        return [$installId, $expiresAtTimestamp];
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data)
    {
        $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');
        return base64_decode(strtr($padded, '-_', '+/'), true);
    }
}

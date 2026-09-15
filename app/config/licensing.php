<?php

if (!defined('APP_START')) {
    die('Direct access not permitted');
}

/**
 * Subscription lock / unlock-code settings.
 * ------------------------------------------------------------
 * See app/helpers/LicenseCode.php for the code format, and
 * tools/generate-license-code.php for how codes are issued.
 *
 * `public_key` only lets the app VERIFY a code — it cannot be used
 * to produce one. The matching private key lives in
 * licensing-authority/private-key.pem, which is .gitignore'd and
 * must NEVER be committed or copied to a client deployment. If you
 * ever need to rotate keys (e.g. the private key leaks), regenerate
 * the pair and update `public_key` below — every unexpired code
 * issued under the old key stops verifying immediately.
 */
return [

    // How many days before expiry to start showing the renewal
    // banner (app stays fully usable).
    'warning_days' => 14,

    // How many days after expiry the app keeps working before it
    // hard-locks. Gives a paying-but-slightly-late client room to
    // renew without an outage.
    'grace_days' => 5,

    'public_key' => <<<'PEM'
    -----BEGIN PUBLIC KEY-----
    MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEx4Pc83EkzAPP4CcHrlVRSrM5eNeK
    zbrRzZV8gH6pT3od/9dC0n/eYZaqPFmO0qusui8anTLgE2IT0DO9JRAu8Q==
    -----END PUBLIC KEY-----
    PEM,

];

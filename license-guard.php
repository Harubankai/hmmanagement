
<?php
// license-guard.php

require_once __DIR__ . '/license-config.php';
require_once __DIR__ . '/RoldLicenseClient.php';

use RoldAdmin\Client\RoldLicenseClient;

$publicKeyPath = __DIR__ . '/config/public_key.pem';

if (!is_file($publicKeyPath)) {
    http_response_code(503);
    exit('License verification unavailable: public key missing.');
}

try {
    $licenseClient = new RoldLicenseClient(
        LICENSE_SERVER_URL,
        LICENSE_KEY,
        $publicKeyPath,
        __DIR__ . '/.license_cache',
        300
    );

    $result = $licenseClient->validate(true);

    if (!($result['valid'] ?? false)) {
        http_response_code(403);
        exit(
            'HMSYSTEM license verification failed. ' .
            'Status: ' . htmlspecialchars(
                (string)($result['status'] ?? 'unknown'),
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }
} catch (\Throwable $e) {
    error_log('HMSYSTEM license check failed: ' . $e->getMessage());
    http_response_code(503);
    exit('License server unavailable. Please try again later.');
}

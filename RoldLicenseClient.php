<?php
/**
 * RoldAdmin - Secure Remote Software License Client SDK
 * 
 * Standalone Client-side License Verifier for PHP Applications.
 * Performs OpenSSL RSA-SHA256 signature verification, replay mitigation,
 * and optional resilient local caching.
 * 
 * Requirements: PHP 8.0+, ext-openssl, ext-curl
 */

declare(strict_types=1);

namespace RoldAdmin\Client;

use RuntimeException;

class RoldLicenseClient
{
    private string $serverUrl;
    private string $licenseKey;
    private string $publicKeyPem;
    private ?string $cacheFilePath;
    private int $cacheTtlSecs;
    private int $clockSkewToleranceSecs;
    private string $clientFingerprint;
    private string $appVersion;

    /**
     * @param string $serverUrl Full URL to /api/check-license.php
     * @param string $licenseKey The issued confidential license key (e.g. ROLD-XXXX-XXXX-XXXX-XXXX)
     * @param string $publicKeyPem RSA Public Key PEM string or path to public_key.pem
     * @param string|null $cacheFilePath Optional path to cache signed payload locally (e.g. __DIR__ . '/.license_cache')
     * @param int $cacheTtlSecs Local cache validity in seconds (default: 86400 = 24 hours)
     * @param int $clockSkewToleranceSecs Maximum allowable client/server clock drift (default: 300s)
     */
    public function __construct(
        string $serverUrl,
        string $licenseKey,
        string $publicKeyPem,
        ?string $cacheFilePath = null,
        int $cacheTtlSecs = 86400,
        int $clockSkewToleranceSecs = 300,
        string $appVersion = '1.0.0'
    ) {
        $this->serverUrl = $serverUrl;
        $this->licenseKey = trim($licenseKey);
        $this->cacheFilePath = $cacheFilePath;
        $this->cacheTtlSecs = $cacheTtlSecs;
        $this->clockSkewToleranceSecs = $clockSkewToleranceSecs;
        $this->appVersion = $appVersion;

        // If a file path was provided for public key, read its content
        if (file_exists($publicKeyPem)) {
            $pemContent = file_get_contents($publicKeyPem);
            if ($pemContent === false) {
                throw new RuntimeException("Could not read public key file: {$publicKeyPem}");
            }
            $this->publicKeyPem = $pemContent;
        } else {
            $this->publicKeyPem = $publicKeyPem;
        }

        // Generate client machine fingerprint (unique to this local XAMPP/OS installation)
        $this->clientFingerprint = $this->generateClientFingerprint();
    }

    /**
     * Validate license status
     * 
     * @param bool $forceRemote If true, ignores local cache and verifies with remote server
     * @return array [ 'valid' => bool, 'status' => string, 'message' => string, 'data' => ?array ]
     */
    public function validate(bool $forceRemote = false): array
    {
        // 1. Try reading valid local cache if remote check is not forced
        if (!$forceRemote && $this->cacheFilePath && file_exists($this->cacheFilePath)) {
            $cached = $this->loadCachedLicense();
            if ($cached !== null) {
                return $cached;
            }
        }

        // 2. Perform live remote verification over HTTPS
        return $this->queryRemoteServer();
    }

    /**
     * Contact the remote license management server
     */
    private function queryRemoteServer(): array
    {
        $nonce = bin2hex(random_bytes(16));
        $requestPayload = [
            'license_key'        => $this->licenseKey,
            'nonce'              => $nonce,
            'client_instance_id' => $this->clientFingerprint,
            'app_version'        => $this->appVersion,
            'timestamp'          => time(),
        ];

        $jsonBody = json_encode($requestPayload);

        $ch = curl_init($this->serverUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 7,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Handle free hosting firewall challenge (InfinityFree aes.js testcookie)
        if (is_string($responseBody) && str_contains($responseBody, '/aes.js')) {
            if (preg_match('/toNumbers\("([a-f0-9]+)"\).*?toNumbers\("([a-f0-9]+)"\).*?toNumbers\("([a-f0-9]+)"\)/s', $responseBody, $matches)) {
                $k = hex2bin($matches[1]);
                $iv = hex2bin($matches[2]);
                $cipher = hex2bin($matches[3]);
                $dec = @openssl_decrypt($cipher, 'AES-128-CBC', $k, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
                if ($dec) {
                    $cookieVal = bin2hex($dec);
                    $retryCh = curl_init($this->serverUrl);
                    curl_setopt_array($retryCh, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => $jsonBody,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER     => [
                            'Content-Type: application/json',
                            'Accept: application/json',
                        ],
                        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        CURLOPT_COOKIE         => "__test={$cookieVal}",
                        CURLOPT_TIMEOUT        => 15,
                        CURLOPT_CONNECTTIMEOUT => 7,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => 0,
                    ]);
                    $responseBody = curl_exec($retryCh);
                    $httpCode = curl_getinfo($retryCh, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($retryCh);
                    curl_close($retryCh);
                }
            }
        }

        if ($responseBody === false || $responseBody === '') {
            // Network outage: fallback to cache if available
            if ($this->cacheFilePath && file_exists($this->cacheFilePath)) {
                $cached = $this->loadCachedLicense(true); // allow expired cache in offline grace period
                if ($cached !== null) {
                    $cached['message'] .= ' (Operating in offline cached grace mode)';
                    return $cached;
                }
            }

            return [
                'valid'   => false,
                'status'  => 'network_error',
                'message' => "Unable to contact license server: {$curlError} (HTTP {$httpCode})",
                'data'    => null,
            ];
        }

        $result = json_decode($responseBody, true);
        if (!is_array($result)) {
            return [
                'valid'   => false,
                'status'  => 'server_error',
                'message' => 'Malformed response from license server.',
                'data'    => null,
            ];
        }

        // Check for server-level errors (rate limits, bad input, etc.)
        if (empty($result['success']) || !isset($result['data'], $result['signature'])) {
            $msg = $result['error'] ?? $result['message'] ?? 'License verification rejected by server.';
            return [
                'valid'   => false,
                'status'  => $result['status'] ?? 'rejected',
                'message' => $msg,
                'data'    => null,
            ];
        }

        $data = $result['data'];
        $signatureBase64 = (string)$result['signature'];

        // 3. Cryptographic Verification: Verify RSA-SHA256 Signature
        $canonicalString = $this->canonicalize($data);
        $signatureValid = $this->verifySignature($canonicalString, $signatureBase64);

        if (!$signatureValid) {
            // Signature verification failed! Payload was tampered with or fake server
            $this->invalidateCache();
            return [
                'valid'   => false,
                'status'  => 'tampered',
                'message' => 'SECURITY VIOLATION: Digital signature verification failed. Response may be forged.',
                'data'    => null,
            ];
        }

        // 4. Replay Attack Mitigation: Verify Nonce
        if (($data['nonce'] ?? '') !== $nonce) {
            return [
                'valid'   => false,
                'status'  => 'replay_detected',
                'message' => 'Replay protection failed: Nonce mismatch.',
                'data'    => null,
            ];
        }

        // 5. Replay Attack Mitigation: Verify Validity Window
        $serverValidUntil = (int)($data['valid_until'] ?? 0);
        $localTime = time();

        // Allow tolerance for clock drift between server and local host
        if ($localTime > ($serverValidUntil + $this->clockSkewToleranceSecs)) {
            return [
                'valid'   => false,
                'status'  => 'response_expired',
                'message' => 'Signed response has expired. Please verify local computer date and time.',
                'data'    => null,
            ];
        }

        // 6. Check License Status
        $isValid = ($data['valid'] === '1' || $data['valid'] === true);
        $status  = (string)($data['status'] ?? 'unknown');

        if ($isValid && $status === 'active') {
            // Save to local cache
            $this->saveCache($result);

            return [
                'valid'   => true,
                'status'  => 'active',
                'message' => 'License successfully verified.',
                'data'    => $data,
            ];
        }

        // Status is suspended, expired, or invalid
        $this->invalidateCache();

        $statusMsg = match ($status) {
            'suspended' => 'Your software license has been suspended by the administrator.',
            'expired'   => 'Your software license has expired.',
            default     => 'Invalid or unrecognized software license.',
        };

        return [
            'valid'   => false,
            'status'  => $status,
            'message' => $statusMsg,
            'data'    => $data,
        ];
    }

    /**
     * Deterministic Canonicalization (identically mirrors RoldAdmin\Signer)
     */
    public function canonicalize(array $data): string
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            $keyStr = (string)$key;
            if (is_bool($value)) {
                $valStr = $value ? '1' : '0';
            } elseif ($value === null) {
                $valStr = '';
            } else {
                $valStr = (string)$value;
            }
            $normalized[$keyStr] = $valStr;
        }

        ksort($normalized, SORT_STRING);

        $pairs = [];
        foreach ($normalized as $k => $v) {
            $pairs[] = "{$k}={$v}";
        }

        return implode('|', $pairs);
    }

    /**
     * Verify RSA-SHA256 signature using OpenSSL
     */
    private function verifySignature(string $canonicalString, string $base64Signature): bool
    {
        $rawSig = base64_decode($base64Signature, true);
        if ($rawSig === false) {
            return false;
        }

        $pubKeyResource = openssl_pkey_get_public($this->publicKeyPem);
        if ($pubKeyResource === false) {
            throw new RuntimeException("Invalid public key provided to license client.");
        }

        $verifyResult = openssl_verify(
            $canonicalString,
            $rawSig,
            $pubKeyResource,
            OPENSSL_ALGO_SHA256
        );

        return $verifyResult === 1;
    }

    /**
     * Load and verify locally cached license record
     */
    private function loadCachedLicense(bool $allowExpiredGrace = false): ?array
    {
        if (!$this->cacheFilePath || !file_exists($this->cacheFilePath)) {
            return null;
        }

        $content = @file_get_contents($this->cacheFilePath);
        if (!$content) {
            return null;
        }

        $cache = json_decode($content, true);
        if (!is_array($cache) || empty($cache['cached_at']) || empty($cache['response'])) {
            return null;
        }

        $cachedAt = (int)$cache['cached_at'];
        $now = time();

        // Check if cache TTL has elapsed
        if (!$allowExpiredGrace && ($now - $cachedAt) > $this->cacheTtlSecs) {
            return null;
        }

        $response = $cache['response'];
        $data = $response['data'] ?? [];
        $sig = $response['signature'] ?? '';

        // Verify cryptographic signature of the cached record to ensure no local file tampering
        $canonical = $this->canonicalize($data);
        if (!$this->verifySignature($canonical, $sig)) {
            $this->invalidateCache();
            return null;
        }

        // Verify expiration date inside cached payload
        if (!empty($data['expires_at'])) {
            $expTs = strtotime($data['expires_at']);
            if ($expTs !== false && $now > $expTs) {
                return null;
            }
        }

        if (($data['valid'] === '1' || $data['valid'] === true) && ($data['status'] === 'active')) {
            return [
                'valid'   => true,
                'status'  => 'active',
                'message' => 'License valid (verified from secure local cache).',
                'data'    => $data,
            ];
        }

        return null;
    }

    /**
     * Store valid signed response in local cache
     */
    private function saveCache(array $fullResponse): void
    {
        if (!$this->cacheFilePath) {
            return;
        }

        $cacheData = [
            'cached_at' => time(),
            'response'  => $fullResponse,
        ];

        @file_put_contents(
            $this->cacheFilePath,
            json_encode($cacheData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
        @chmod($this->cacheFilePath, 0600);
    }

    /**
     * Remove local cache
     */
    public function invalidateCache(): void
    {
        if ($this->cacheFilePath && file_exists($this->cacheFilePath)) {
            @unlink($this->cacheFilePath);
        }
    }

    /**
     * Generate machine/host fingerprint for node-locking
     */
    private function generateClientFingerprint(): string
    {
        $elements = [
            php_uname('s'), // OS name
            php_uname('n'), // Hostname
            $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            dirname(__DIR__),
        ];
        return hash('sha256', implode('|', $elements));
    }
}

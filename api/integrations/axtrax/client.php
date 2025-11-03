<?php
declare(strict_types=1);

/**
 * Lightweight AxTrax Pro REST client scaffold.
 *
 * Notes for implementers once Rosslare delivers the specs:
 * -------------------------------------------------------
 *  - Populate the endpoint paths and payload structure inside updateMemberValidity().
 *  - Confirm authentication scheme (likely header token) and adjust buildHeaders().
 *  - Consider logging to api/logs if the remote side returns non-2xx responses.
 */
final class AxtraxClient
{
    private string $baseUrl;
    private string $apiKey;
    private ?string $siteId;
    private int $timeoutSeconds;

    /**
     * @param array{base_url?:string,api_key?:string,site_id?:string,timeout_s?:int} $cfg
     */
    public function __construct(array $cfg)
    {
        $this->baseUrl        = rtrim((string)($cfg['base_url'] ?? ''), '/');
        $this->apiKey         = (string)($cfg['api_key'] ?? '');
        $this->siteId         = isset($cfg['site_id']) ? (string)$cfg['site_id'] : null;
        $this->timeoutSeconds = isset($cfg['timeout_s']) ? (int)$cfg['timeout_s'] : 8;

        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new RuntimeException('AxTrax configuration incomplete (base_url/api_key required).');
        }
    }

    /**
     * Factory that loads config/payments.php (if present) and extracts ['axtrax'].
     */
    public static function buildFromConfig(): self
    {
        $configPath = dirname(__DIR__, 3) . '/config/payments.php';

        if (!is_readable($configPath)) {
            throw new RuntimeException('AxTrax config missing: ' . $configPath);
        }

        /** @var array $cfg */
        $cfg = require $configPath;
        if (!isset($cfg['axtrax']) || !is_array($cfg['axtrax'])) {
            throw new RuntimeException('AxTrax config missing "axtrax" section.');
        }

        return new self($cfg['axtrax']);
    }

    /**
     * Stage hook for reactivating a member by updating valid-until.
     *
     * @return array<string,mixed> The decoded response body once implemented.
     */
    public function updateMemberValidity(int $memberId, string $validUntil): array
    {
        if ($memberId <= 0) {
            throw new InvalidArgumentException('Member ID must be positive.');
        }
        if ($validUntil === '') {
            throw new InvalidArgumentException('validUntil cannot be empty.');
        }

        // TODO: Replace with the actual AxTrax REST path + payload once provided.
        // Example future implementation sketch:
        //   $endpoint = $this->baseUrl . '/members/' . $memberId . '/validity';
        //   $payload  = ['valid_until' => $validUntil, 'site_id' => $this->siteId];
        //   return $this->postJson($endpoint, $payload);

        throw new LogicException('AxTrax REST endpoint details pending from vendor.');
    }

    /**
     * Internal helper to perform a POST once endpoint details are known.
     *
     * @param array<string,mixed> $payload
     */
    private function postJson(string $url, array $payload): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to init cURL for AxTrax request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->buildHeaders(),
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('AxTrax request failed: ' . $err);
        }

        $decoded = json_decode($response, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('AxTrax response not JSON: ' . json_last_error_msg());
        }

        if ($httpCode >= 400) {
            throw new RuntimeException(
                sprintf('AxTrax API error (HTTP %d): %s', $httpCode, $response)
            );
        }

        return is_array($decoded) ? $decoded : ['raw' => $response];
    }

    /**
     * @return string[]
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        // TODO: Confirm the required auth header shape.
        $headers[] = 'Authorization: Bearer ' . $this->apiKey;

        if ($this->siteId) {
            $headers[] = 'X-AxTrax-Site: ' . $this->siteId;
        }

        return $headers;
    }
}


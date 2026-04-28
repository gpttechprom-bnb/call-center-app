<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinotelApi
{
    /**
     * Binotel call-record responses can include regular page URLs, CRM links and
     * cabinet overlays. Prefer fields that are explicitly about the recording.
     */
    private const RECORDING_URL_KEYS = [
        'url',
        'record',
        'recordurl',
        'recordingurl',
        'callrecordurl',
        'audiourl',
        'downloadurl',
        'fileurl',
        'linktocallrecordurl',
        'linktocallrecord',
        'linktocallrecordinmybusiness',
    ];

    private const AUDIO_FILE_EXTENSIONS = [
        'aac',
        'flac',
        'm4a',
        'mp3',
        'mp4',
        'oga',
        'ogg',
        'opus',
        'wav',
        'webm',
    ];

    protected string $key;

    protected string $secret;

    protected string $apiHost = 'https://api.binotel.com/api/';

    protected string $apiVersion = '4.0';

    protected string $apiFormat = 'json';

    protected bool $disableSSLChecks = false;

    protected int $timeout = 20;

    public bool $debug = false;

    public const FIFTEEN_HOURS_IN_SECONDS = 54000;

    public const BINOTEL_API_MODULE = 3;

    public function __construct(
        ?string $key = null,
        ?string $secret = null,
        ?string $apiHost = null,
        ?string $apiVersion = null,
        ?string $apiFormat = null
    ) {
        $config = config('binotel.api', []);

        $this->key = (string) ($key ?? ($config['key'] ?? ''));
        $this->secret = (string) ($secret ?? ($config['secret'] ?? ''));
        $this->apiHost = (string) ($apiHost ?? ($config['host'] ?? $this->apiHost));
        $this->apiVersion = (string) ($apiVersion ?? ($config['version'] ?? $this->apiVersion));
        $this->apiFormat = (string) ($apiFormat ?? ($config['format'] ?? $this->apiFormat));
        $this->disableSSLChecks = (bool) ($config['disable_ssl_checks'] ?? false);
        $this->timeout = (int) ($config['timeout'] ?? $this->timeout);
        $this->debug = (bool) ($config['debug'] ?? false);
    }

    /**
     * Sends a request to the Binotel API.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|string|int|float|bool|null
     */
    public function sendRequest(string $url, array $params)
    {
        if ($this->key === '' || $this->secret === '') {
            $this->debugLog('Binotel API credentials are missing.');

            return null;
        }

        $params['key'] = $this->key;
        $params['secret'] = $this->secret;

        if ($this->debug) {
            $this->debugLog('Send request.', [
                'endpoint' => $url,
                'payload' => $this->maskSensitiveData($params),
            ]);
        }

        $response = $this->httpClient()->post($this->buildEndpointUrl($url), $params);

        if ($this->debug) {
            $this->debugLog('Server response code.', [
                'endpoint' => $url,
                'status' => $response->status(),
            ]);
        }

        if (! $response->successful()) {
            $this->debugLog('Server error.', [
                'endpoint' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $decodedResult = json_decode($response->body(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->debugLog('Server sent invalid data.', [
                'endpoint' => $url,
                'body' => $response->body(),
                'jsonError' => json_last_error_msg(),
            ]);

            return null;
        }

        return $decodedResult;
    }

    public function getCallRecordUrl(string $generalCallId): ?string
    {
        $callRecordLink = $this->sendRequest('stats/call-record', [
            'generalCallID' => $generalCallId,
        ]);

        return $this->extractRecordingUrl($callRecordLink);
    }

    private function httpClient(): PendingRequest
    {
        $request = Http::asJson()
            ->acceptJson()
            ->timeout($this->timeout);

        if ($this->disableSSLChecks) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    private function buildEndpointUrl(string $url): string
    {
        return rtrim($this->apiHost, '/').'/'.$this->apiVersion.'/'.ltrim($url, '/').'.'.$this->apiFormat;
    }

    /**
     * @param  array<string, mixed>|string|int|float|bool|null  $response
     */
    private function extractRecordingUrl($response): ?string
    {
        if (is_string($response)) {
            return $this->isUrl($response) && ! $this->isIgnoredRecordingUrl($response)
                ? trim($response)
                : null;
        }

        if (! is_array($response)) {
            return null;
        }

        $preferredUrl = $this->findPreferredRecordingUrl($response);

        if ($preferredUrl !== null) {
            return $preferredUrl;
        }

        return $this->findFirstAudioLikeUrl($response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function findPreferredRecordingUrl(array $response): ?string
    {
        foreach (self::RECORDING_URL_KEYS as $recordingUrlKey) {
            $url = $this->findUrlByNormalizedKey($response, $recordingUrlKey);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function findUrlByNormalizedKey(array $response, string $normalizedKey): ?string
    {
        foreach ($response as $key => $value) {
            if (
                is_string($value)
                && $this->normalizeResponseKey((string) $key) === $normalizedKey
                && $this->isUrl($value)
                && ! $this->isIgnoredRecordingUrl($value)
            ) {
                return trim($value);
            }

            if (is_array($value)) {
                $nestedUrl = $this->findUrlByNormalizedKey($value, $normalizedKey);

                if ($nestedUrl !== null) {
                    return $nestedUrl;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function findFirstAudioLikeUrl(array $response): ?string
    {
        foreach ($response as $value) {
            if (
                is_string($value)
                && $this->isUrl($value)
                && $this->looksLikeRecordingUrl($value)
                && ! $this->isIgnoredRecordingUrl($value)
            ) {
                return trim($value);
            }

            if (is_array($value)) {
                $nestedUrl = $this->findFirstAudioLikeUrl($value);

                if ($nestedUrl !== null) {
                    return $nestedUrl;
                }
            }
        }

        return null;
    }

    private function normalizeResponseKey(string $key): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $key));
    }

    private function looksLikeRecordingUrl(string $value): bool
    {
        $value = strtolower(trim($value));

        if ($value === '') {
            return false;
        }

        foreach (self::AUDIO_FILE_EXTENSIONS as $extension) {
            if (preg_match('/\.'.preg_quote($extension, '/').'(\?|#|&|$)/', $value) === 1) {
                return true;
            }
        }

        return str_contains($value, 'action=generatefile')
            || str_contains($value, 'call-record')
            || str_contains($value, 'call_record')
            || str_contains($value, 'recording');
    }

    private function isIgnoredRecordingUrl(string $value): bool
    {
        $value = trim($value);
        $parts = parse_url($value);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));
        $query = strtolower((string) ($parts['query'] ?? ''));

        if ($host === '') {
            return false;
        }

        if (! str_contains($host, 'binotel.ua')) {
            return false;
        }

        if (str_contains($value, '#/')) {
            return true;
        }

        if (str_starts_with($path, '/f/pbx')) {
            return true;
        }

        if (str_contains($query, 'module=history')) {
            return true;
        }

        return str_contains($query, 'sacte=ovl-link-pb')
            && ! str_contains($query, 'action=generatefile');
    }

    private function isUrl(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function maskSensitiveData(array $payload): array
    {
        if (array_key_exists('key', $payload)) {
            $payload['key'] = '***';
        }

        if (array_key_exists('secret', $payload)) {
            $payload['secret'] = '***';
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function debugLog(string $message, array $context = []): void
    {
        Log::channel((string) config('binotel.log_channel', 'stack'))
            ->debug('Binotel API: '.$message, $context);
    }
}

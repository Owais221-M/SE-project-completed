<?php
/**
 * Centralized API helper with retry logic, timeout, and rate-limit handling.
 * Used across all PHP files that make external HTTP requests (Binance, Ganache).
 */

/**
 * Fetch a URL with retry logic, timeout, and rate-limit/backoff handling.
 *
 * @param string $url       The URL to fetch
 * @param int    $retries   Number of attempts (default 3)
 * @param float  $timeout   Connection timeout in seconds (default 5)
 * @param array  $postData  If provided, sends as POST with JSON body
 * @return string|false     The response body, or false on total failure
 */
function fetchWithRetry(string $url, int $retries = 3, float $timeout = 5, ?array $postData = null)
{
    $lastError = '';

    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        $contextOptions = [
            'http' => [
                'timeout'       => $timeout,
                'ignore_errors' => true,      // so we can read 4xx/5xx bodies
                'header'        => "Accept: application/json\r\n",
            ]
        ];

        if ($postData !== null) {
            $jsonBody = json_encode($postData);
            $contextOptions['http']['method']  = 'POST';
            $contextOptions['http']['header'] .= "Content-Type: application/json\r\n";
            $contextOptions['http']['content']  = $jsonBody;
        }

        $context  = stream_context_create($contextOptions);
        $response = @file_get_contents($url, false, $context);

        // Parse HTTP status from $http_response_header
        $httpCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $m)) {
                    $httpCode = (int) $m[1];
                }
            }
        }

        // Rate limited (HTTP 429) — wait and retry
        if ($httpCode === 429) {
            $retryAfter = 2 * $attempt; // exponential backoff
            error_log("[API] Rate limited on $url (attempt $attempt/$retries). Waiting {$retryAfter}s.");
            sleep($retryAfter);
            continue;
        }

        // Server error (5xx) — retry
        if ($httpCode >= 500 && $attempt < $retries) {
            error_log("[API] Server error $httpCode from $url (attempt $attempt/$retries).");
            usleep(500000 * $attempt); // 0.5s * attempt
            continue;
        }

        // Total failure to connect
        if ($response === false) {
            $lastError = "Connection failed to $url";
            error_log("[API] $lastError (attempt $attempt/$retries).");
            usleep(500000 * $attempt);
            continue;
        }

        // Success (or a non-retryable HTTP error)
        return $response;
    }

    error_log("[API] All $retries attempts failed for $url. Last error: $lastError");
    return false;
}

/**
 * Fetch and JSON-decode a URL with retry, returning decoded array or null.
 *
 * @param string $url
 * @param int    $retries
 * @param float  $timeout
 * @return array|null
 */
function fetchJsonWithRetry(string $url, int $retries = 3, float $timeout = 5): ?array
{
    $raw = fetchWithRetry($url, $retries, $timeout);
    if ($raw === false) return null;

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        error_log("[API] Invalid JSON from $url: " . substr($raw, 0, 200));
        return null;
    }

    return $data;
}

/**
 * Fetch a single Binance ticker price (e.g., BTCUSDT).
 *
 * @param string $symbol  e.g., 'BTCUSDT'
 * @return float|null      The price, or null on failure
 */
function fetchBinancePrice(string $symbol): ?float
{
    $data = fetchJsonWithRetry(
        "https://api.binance.com/api/v3/ticker/price?symbol=$symbol"
    );

    return isset($data['price']) ? (float) $data['price'] : null;
}

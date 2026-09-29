<?php
declare(strict_types=1);

final class HttpError extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $body, string $message)
    {
        parent::__construct($message);
    }
}

/**
 * Small curl wrapper. Returns [status, decoded JSON (or raw string)].
 */
function http_request(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => array_merge(['User-Agent: vivahx-editor'], $headers),
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    if ($response === false) {
        throw new HttpError(0, '', "Could not reach $url: $error");
    }
    $decoded = json_decode($response, true);
    return [$status, $decoded ?? $response];
}

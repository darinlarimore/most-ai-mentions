<?php

namespace App\Enums;

enum CrawlErrorCategory: string
{
    case Timeout = 'timeout';
    case DnsFailure = 'dns_failure';
    case ConnectionError = 'connection_error';
    case SslError = 'ssl_error';
    case HttpClientError = 'http_client_error';
    case HttpServerError = 'http_server_error';
    case EmptyResponse = 'empty_response';
    case Blocked = 'blocked';
    case RedirectToNonHomepage = 'redirect_to_non_homepage';
    case ParseError = 'parse_error';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Timeout => 'Timeout',
            self::DnsFailure => 'DNS Failure',
            self::ConnectionError => 'Connection Error',
            self::SslError => 'SSL Error',
            self::HttpClientError => 'HTTP Client Error',
            self::HttpServerError => 'HTTP Server Error',
            self::EmptyResponse => 'Empty Response',
            self::Blocked => 'Blocked',
            self::RedirectToNonHomepage => 'Redirect to Non-Homepage',
            self::ParseError => 'Parse Error',
            self::Unknown => 'Unknown',
        };
    }

    /**
     * Classify a throwable into an error category using cURL error codes and message patterns.
     */
    public static function fromThrowable(\Throwable $e): self
    {
        $message = mb_strtolower($e->getMessage());
        $code = $e->getCode();

        // cURL error codes
        if ($code === 28 || str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return self::Timeout;
        }

        if ($code === 6 || str_contains($message, 'could not resolve host') || str_contains($message, 'dns')) {
            return self::DnsFailure;
        }

        if ($code === 7 || str_contains($message, 'connection refused') || str_contains($message, 'failed to connect')) {
            return self::ConnectionError;
        }

        if (in_array($code, [35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83]) || str_contains($message, 'ssl') || str_contains($message, 'certificate')) {
            return self::SslError;
        }

        // HTTP status codes (from Guzzle-style exceptions)
        if (str_contains($message, '403') || str_contains($message, '429') || str_contains($message, 'forbidden') || str_contains($message, 'cloudflare')) {
            return self::Blocked;
        }

        if (preg_match('/\b4\d{2}\b/', $message)) {
            return self::HttpClientError;
        }

        if (preg_match('/\b5\d{2}\b/', $message)) {
            return self::HttpServerError;
        }

        if (str_contains($message, 'empty') || str_contains($message, 'no response') || $code === 52) {
            return self::EmptyResponse;
        }

        if ($e instanceof \DOMException || str_contains($message, 'parse') || str_contains($message, 'malformed')) {
            return self::ParseError;
        }

        return self::Unknown;
    }
}

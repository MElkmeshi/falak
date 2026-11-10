<?php

namespace Falak\Billing\Infrastructure\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Libyan gateways only answer Libyan source addresses and some serve incomplete certificate
 * chains, so every call can go through a proxy with Libyan egress, present a client
 * certificate and trust one extra CA file on top of the system store.
 */
final class GatewayHttp
{
    public static function request(
        ?string $proxy = null,
        ?string $clientCert = null,
        ?string $clientKey = null,
        ?string $extraCa = null,
        int $timeout = 30,
        int $connectTimeout = 30,
    ): PendingRequest {
        $options = ['timeout' => $timeout, 'connect_timeout' => $connectTimeout, 'proxy' => self::filled($proxy) ? trim((string) $proxy) : ''];

        if (self::filled($clientCert)) {
            $options['cert'] = self::file($clientCert);
        }

        if (self::filled($clientKey)) {
            $options['ssl_key'] = self::file($clientKey);
        }

        if (self::filled($extraCa)) {
            $options['verify'] = self::file($extraCa);
            $systemCaDirectory = openssl_get_cert_locations()['default_cert_dir'] ?? null;

            if (is_string($systemCaDirectory) && is_dir($systemCaDirectory)) {
                $options['curl'] = [CURLOPT_CAPATH => $systemCaDirectory];
            }
        }

        return Http::withOptions($options);
    }

    private static function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private static function file(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("The payment gateway file {$path} is missing.");
        }

        return $path;
    }
}

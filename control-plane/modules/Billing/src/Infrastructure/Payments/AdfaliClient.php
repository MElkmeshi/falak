<?php

namespace Falak\Billing\Infrastructure\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AdfaliClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $merchantMobile,
        private readonly string $merchantPin,
        private readonly string $servicePassword,
    ) {}

    public function initiate(string $customerMobile, float $amount): string
    {
        $value = $this->call('DoPTrans', 'DoPTransResult', [
            'Mobile' => $this->merchantMobile,
            'Pin' => $this->merchantPin,
            'Cmobile' => $customerMobile,
            'Amount' => $amount,
            'PW' => $this->servicePassword,
        ]);

        return match (strtoupper($value)) {
            'PW1' => throw new RuntimeException('Adfali rejected the service password.'),
            'PW' => throw new RuntimeException('Adfali rejected the merchant PIN.'),
            'LIMIT' => throw new RuntimeException('The amount is over your Adfali limit.'),
            'ACC' => throw new RuntimeException('No Adfali account for this mobile number.'),
            'BAL' => throw new RuntimeException('Not enough balance in the Adfali account.'),
            default => $value,
        };
    }

    public function confirm(string $sessionId, string $customerPin): bool
    {
        return $this->call('OnlineConfTrans', 'OnlineConfTransResult', [
            'Mobile' => $this->merchantMobile,
            'Pin' => $customerPin,
            'sessionID' => $sessionId,
            'PW' => $this->servicePassword,
        ]) === 'OK';
    }

    private function call(string $method, string $resultTag, array $params): string
    {
        $fields = '';
        foreach ($params as $key => $value) {
            $fields .= "<{$key}>".htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')."</{$key}>";
        }

        $envelope = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            ."<{$method} xmlns=\"http://tempuri.org/\">{$fields}</{$method}></soap:Body></soap:Envelope>";

        try {
            $response = Http::withHeaders(['SOAPAction' => "http://tempuri.org/{$method}"])
                ->timeout(30)->withBody($envelope, 'text/xml; charset=utf-8')->post(rtrim($this->baseUrl, '/'));
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach Adfali.');
        }

        if (! $response->ok()) {
            throw new RuntimeException("Adfali answered HTTP {$response->status()}.");
        }

        $xml = @simplexml_load_string($response->body());
        $nodes = $xml === false ? [] : ($xml->xpath("//*[local-name()='{$resultTag}']") ?: []);

        if ($nodes === []) {
            throw new RuntimeException('Adfali sent an unreadable answer.');
        }

        return trim((string) $nodes[0]);
    }
}

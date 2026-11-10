<?php

namespace Falak\Billing\Infrastructure\Payments;

use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

final class AdfaliClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $merchantMobile,
        private readonly string $merchantPin,
        private readonly string $servicePassword,
        private readonly ?string $proxy = null,
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
            'PW1' => throw new RuntimeException('Edfali rejected the service password.'),
            'PW' => throw new RuntimeException('Edfali rejected the merchant PIN.'),
            'LIMIT' => throw new RuntimeException('The amount is over your Edfali limit.'),
            'ACC' => throw new RuntimeException('No Edfali account for this mobile number.'),
            'BAL' => throw new RuntimeException('Not enough balance in the Edfali account.'),
            default => $value,
        };
    }

    public function confirm(string $sessionId, string $customerPin): bool
    {
        return strtoupper($this->call('OnlineConfTrans', 'OnlineConfTransResult', [
            'Mobile' => $this->merchantMobile,
            'Pin' => $customerPin,
            'sessionID' => $sessionId,
            'PW' => $this->servicePassword,
        ])) === 'OK';
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
            $response = GatewayHttp::request($this->proxy)->withHeaders(['SOAPAction' => "http://tempuri.org/{$method}"])
                ->withBody($envelope, 'text/xml; charset=utf-8')->post(rtrim($this->baseUrl, '/'));
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach Edfali.');
        }

        if (! $response->ok()) {
            throw new RuntimeException("Edfali answered HTTP {$response->status()}.");
        }

        $xml = @simplexml_load_string($response->body());
        $nodes = $xml === false ? [] : ($xml->xpath("//*[local-name()='{$resultTag}']") ?: []);

        if ($nodes === []) {
            throw new RuntimeException('Edfali sent an unreadable answer.');
        }

        return trim((string) $nodes[0]);
    }
}

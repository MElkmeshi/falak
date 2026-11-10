<?php

namespace Falak\Billing\Infrastructure\Payments;

use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

final class MoamalatClient
{
    /**
     * @param  array<string, mixed>  $cfg
     */
    public function __construct(private readonly array $cfg) {}

    public static function fromConfig(): self
    {
        return new self((array) config('billing.providers.moamalat'));
    }

    public function host(): string
    {
        return ($this->cfg['production'] ?? false) ? 'npg.moamalat.net' : 'tnpg.moamalat.net';
    }

    public function lightboxOrigin(): string
    {
        return 'https://'.$this->host().':6006';
    }

    public function lightboxScript(): string
    {
        return $this->lightboxOrigin().'/js/lightbox.js';
    }

    public static function toDirhams(string|float $amount): int
    {
        return (int) round(((float) $amount) * 1000);
    }

    /**
     * @return array{MID: string, TID: string, AmountTrxn: int, MerchantReference: string, TrxDateTime: int, SecureHash: string}
     */
    public function checkout(string $reference, string|float $amount, ?int $now = null): array
    {
        $now ??= time();
        $dirhams = self::toDirhams($amount);
        $mid = (string) $this->cfg['merchant_id'];
        $tid = (string) $this->cfg['terminal_id'];

        return [
            'MID' => $mid,
            'TID' => $tid,
            'AmountTrxn' => $dirhams,
            'MerchantReference' => $reference,
            'TrxDateTime' => $now,
            'SecureHash' => $this->sign("Amount={$dirhams}&DateTimeLocalTrxn={$now}&MerchantId={$mid}&MerchantReference={$reference}&TerminalId={$tid}", (string) $this->cfg['secret_key']),
        ];
    }

    /**
     * Every transaction Moamalat holds for a merchant reference (and network reference when given).
     *
     * @return list<array<string, mixed>>
     */
    public function transactions(string $merchantReference, ?string $networkReference = null): array
    {
        $now = time();
        $mid = (string) $this->cfg['merchant_id'];
        $tid = (string) $this->cfg['terminal_id'];

        try {
            $response = GatewayHttp::request($this->cfg['proxy'] ?? null, extraCa: $this->cfg['intermediate_ca_path'] ?? null, timeout: 60, connectTimeout: 60)
                ->acceptJson()->asJson()
                ->post('https://'.$this->host().'/cube/paylink.svc/api/FilterTransactions', [
                    'NetworkReference' => $networkReference ?? '',
                    'MerchantReference' => $merchantReference,
                    'TerminalId' => $tid,
                    'MerchantId' => $mid,
                    'DisplayLength' => 20,
                    'DisplayStart' => 0,
                    'DateTimeLocalTrxn' => $now,
                    'SecureHash' => $this->sign("DateTimeLocalTrxn={$now}&MerchantId={$mid}&TerminalId={$tid}", (string) $this->cfg['secret_key']),
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach Moamalat.');
        }

        if (! $response->ok() || ! is_array($response->json())) {
            throw new RuntimeException("Moamalat answered HTTP {$response->status()}.");
        }

        $found = [];

        foreach ((array) ($response->json('Transactions') ?? []) as $day) {
            foreach ((array) (is_array($day) ? ($day['DateTransactions'] ?? []) : []) as $transaction) {
                if (is_array($transaction)) {
                    $found[] = $transaction;
                }
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $notification
     */
    public function notificationIsAuthentic(array $notification): bool
    {
        $key = (string) ($this->cfg['notification_key'] ?? '');
        $hash = (string) ($notification['SecureHash'] ?? '');

        if ($key === '' || $hash === '' || (string) ($notification['MerchantId'] ?? '') !== (string) $this->cfg['merchant_id']
            || (string) ($notification['TerminalId'] ?? '') !== (string) $this->cfg['terminal_id']) {
            return false;
        }

        $data = sprintf(
            'Amount=%s&Currency=%s&DateTimeLocalTrxn=%s&MerchantId=%s&TerminalId=%s',
            $notification['Amount'] ?? '', $notification['Currency'] ?? '', $notification['DateTimeLocalTrxn'] ?? '',
            $notification['MerchantId'] ?? '', $notification['TerminalId'] ?? '',
        );

        return hash_equals(strtoupper($this->sign($data, $key)), strtoupper($hash));
    }

    private function sign(string $data, string $hexKey): string
    {
        $key = @hex2bin($hexKey);

        if ($key === false || $key === '') {
            throw new RuntimeException('The Moamalat key is not valid hex.');
        }

        return hash_hmac('sha256', $data, $key);
    }
}

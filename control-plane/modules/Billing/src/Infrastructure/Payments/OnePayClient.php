<?php

namespace Falak\Billing\Infrastructure\Payments;

use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

final class OnePayClient
{
    /**
     * @param  array<string, mixed>  $cfg
     */
    public function __construct(private readonly array $cfg) {}

    public function signIn(): string
    {
        $content = $this->post((string) $this->cfg['signin_url'], [
            'userId' => (int) $this->cfg['user_id'],
            'pin' => (string) $this->cfg['pin'],
            'providerId' => (int) $this->cfg['provider_id'],
            'authUserType' => (int) $this->cfg['auth_user_type'],
        ])['content'] ?? null;

        if (! is_array($content) || ! is_string($content['value'] ?? null) || $content['value'] === '') {
            throw new RuntimeException('The wallet service refused our sign-in.');
        }

        return $content['value'];
    }

    public function openSession(string $identityCard, float $amount, string $transactionId): string
    {
        $response = $this->post((string) $this->cfg['init_url'], [
            'amount' => $amount,
            'identityCard' => $identityCard,
            'transactionId' => $transactionId,
            'onlineOperation' => 1,
        ], $this->signIn());

        $content = $response['content'] ?? null;

        if (! is_array($content) || ! is_string($content['value'] ?? null) || $content['value'] === '') {
            throw new RuntimeException(self::message($response, 'The wallet service could not start the payment.'));
        }

        return $content['value'];
    }

    /**
     * @return array{success: bool, message: ?string, trace_id: ?string}
     */
    public function completeSession(string $sessionToken, string $otp): array
    {
        $response = $this->post((string) $this->cfg['complete_url'], ['otp' => $otp], $sessionToken);

        return [
            'success' => (int) ($response['type'] ?? 0) === 1,
            'message' => self::message($response, null),
            'trace_id' => is_scalar($response['traceId'] ?? null) ? (string) $response['traceId'] : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function transactions(string $transactionId, \DateTimeInterface $around): array
    {
        $from = \DateTimeImmutable::createFromInterface($around);
        $response = $this->post((string) $this->cfg['report_url'], [
            'fromDate' => $from->modify('-5 hours')->format(DATE_ATOM),
            'toDate' => $from->modify('+5 hours')->format(DATE_ATOM),
            'transactionType' => 0,
            'providerTransactionId' => $transactionId,
            'page' => 0,
        ], $this->signIn());

        $list = $response['content']['transactionList'] ?? [];

        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload, ?string $bearer = null): array
    {
        $request = GatewayHttp::request(
            $this->cfg['proxy'] ?? null,
            $this->cfg['cert_path'] ?? null,
            $this->cfg['key_path'] ?? null,
            $this->cfg['ca_path'] ?? null,
        )->acceptJson()->asJson();

        if ($bearer !== null) {
            $request = $request->withToken($bearer);
        }

        try {
            $response = $request->post(rtrim((string) $this->cfg['base_url'], '/').'/'.ltrim($path, '/'), $payload);
        } catch (ConnectionException) {
            throw new RuntimeException('Could not reach the wallet service.');
        }

        $json = $response->json();

        if (! $response->ok() || ! is_array($json)) {
            throw new RuntimeException(is_array($json) ? self::message($json, "The wallet service answered HTTP {$response->status()}.") : "The wallet service answered HTTP {$response->status()}.");
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private static function message(array $response, ?string $fallback): ?string
    {
        $first = is_array($response['messages'] ?? null) ? ($response['messages'][0] ?? null) : null;

        return is_string($first) && $first !== '' ? $first : $fallback;
    }
}

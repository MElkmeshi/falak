<?php

namespace Falak\Billing\Application\Payments;

use Illuminate\Validation\ValidationException;

final class PaymentGateways
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    /**
     * @param  iterable<PaymentGateway>  $gateways
     */
    public function __construct(iterable $gateways)
    {
        foreach ($gateways as $gateway) {
            $this->gateways[$gateway->key()] = $gateway;
        }
    }

    /**
     * @return list<PaymentGateway>
     */
    public function enabled(): array
    {
        return array_values(array_filter($this->gateways, fn (PaymentGateway $g) => $g->enabled()));
    }

    public function get(string $key, ?string $currency = null): PaymentGateway
    {
        $gateway = $this->gateways[$key] ?? null;

        if ($gateway === null || ! $gateway->enabled() || ($currency !== null && ! in_array($currency, $gateway->currencies(), true))) {
            throw ValidationException::withMessages(['provider' => 'This payment method is not available.']);
        }

        return $gateway;
    }

    public function find(string $key): ?PaymentGateway
    {
        return $this->gateways[$key] ?? null;
    }

    public function options(): array
    {
        return array_map(fn (PaymentGateway $g) => [
            'key' => $g->key(),
            'label' => $g->label(),
            'fields' => $g->startFields(),
            'currencies' => $g->currencies(),
        ], $this->enabled());
    }
}

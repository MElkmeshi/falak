<?php

namespace Falak\Billing\Application\Payments;

use Falak\Billing\Domain\Models\Payment;

interface PaymentGateway
{
    public function key(): string;

    public function label(): string;

    public function enabled(): bool;

    /**
     * @return list<string>
     */
    public function currencies(): array;

    /**
     * @return list<array{name: string, label: string, type: string, placeholder?: string}>
     */
    public function startFields(): array;

    /**
     * @param  array<string, string>  $input
     */
    public function start(Payment $payment, array $input): GatewayStep;

    /**
     * @param  array<string, string>  $input
     */
    public function confirm(Payment $payment, array $input): GatewayStep;
}

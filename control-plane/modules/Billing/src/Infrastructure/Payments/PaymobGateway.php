<?php

namespace Falak\Billing\Infrastructure\Payments;

use Falak\Billing\Application\Payments\GatewayStep;
use Falak\Billing\Application\Payments\PaymentGateway;
use Falak\Billing\Domain\Models\Payment;

final class PaymobGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'paymob';
    }

    public function label(): string
    {
        return (string) config('billing.providers.paymob.label');
    }

    public function enabled(): bool
    {
        return false;
    }

    public function currencies(): array
    {
        return ['EGP'];
    }

    public function startFields(): array
    {
        return [];
    }

    public function start(Payment $payment, array $input): GatewayStep
    {
        return GatewayStep::failed('Paymob needs merchant credentials before it can take payments.');
    }

    public function confirm(Payment $payment, array $input): GatewayStep
    {
        return GatewayStep::failed('Paymob needs merchant credentials before it can take payments.');
    }
}

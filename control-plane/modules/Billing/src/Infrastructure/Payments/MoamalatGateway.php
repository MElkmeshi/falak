<?php

namespace Falak\Billing\Infrastructure\Payments;

use Falak\Billing\Application\Payments\GatewayStep;
use Falak\Billing\Application\Payments\PaymentGateway;
use Falak\Billing\Domain\Models\Payment;

final class MoamalatGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'moamalat';
    }

    public function label(): string
    {
        return (string) config('billing.providers.moamalat.label');
    }

    public function enabled(): bool
    {
        return false;
    }

    public function startFields(): array
    {
        return [];
    }

    public function start(Payment $payment, array $input): GatewayStep
    {
        return GatewayStep::failed('Moamalat needs merchant credentials before it can take payments.');
    }

    public function confirm(Payment $payment, array $input): GatewayStep
    {
        return GatewayStep::failed('Moamalat needs merchant credentials before it can take payments.');
    }
}

<?php

namespace Falak\Billing\Infrastructure\Payments;

use Falak\Billing\Application\Payments\GatewayStep;
use Falak\Billing\Application\Payments\PaymentGateway;
use Falak\Billing\Domain\Models\Payment;
use Illuminate\Validation\ValidationException;

final class ManualGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return (string) config('billing.providers.manual.label');
    }

    public function enabled(): bool
    {
        return (bool) config('billing.providers.manual.enabled');
    }

    public function currencies(): array
    {
        return (array) config('billing.currencies');
    }

    public function startFields(): array
    {
        return [
            ['name' => 'method', 'label' => 'Paid with (InstaPay, Vodafone Cash, Sadad, bank…)', 'type' => 'text', 'placeholder' => 'InstaPay'],
            ['name' => 'reference', 'label' => 'Transaction reference', 'type' => 'text', 'placeholder' => 'Reference shown on your receipt'],
        ];
    }

    public function start(Payment $payment, array $input): GatewayStep
    {
        $reference = trim((string) ($input['reference'] ?? ''));

        if (strlen($reference) < 4) {
            throw ValidationException::withMessages(['reference' => 'Enter the transaction reference from your receipt.']);
        }

        $payment->forceFill([
            'reference' => substr($reference, 0, 190),
            'payer' => substr(trim((string) ($input['method'] ?? 'manual')), 0, 120),
        ])->save();

        return GatewayStep::review((string) config('billing.providers.manual.instructions').' Status: waiting for review.');
    }

    public function confirm(Payment $payment, array $input): GatewayStep
    {
        return GatewayStep::review('This payment is waiting for review.');
    }
}

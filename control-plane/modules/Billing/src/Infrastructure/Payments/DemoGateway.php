<?php

namespace Falak\Billing\Infrastructure\Payments;

use Falak\Billing\Application\Payments\GatewayStep;
use Falak\Billing\Application\Payments\PaymentGateway;
use Falak\Billing\Domain\Models\Payment;
use Illuminate\Validation\ValidationException;

final class DemoGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'demo';
    }

    public function label(): string
    {
        return (string) config('billing.providers.demo.label', 'Demo wallet');
    }

    public function enabled(): bool
    {
        return (bool) config('billing.providers.demo.enabled');
    }

    public function currencies(): array
    {
        return (array) config('billing.currencies');
    }

    public function startFields(): array
    {
        return [['name' => 'mobile', 'label' => 'Mobile number', 'type' => 'tel', 'placeholder' => '0912345678']];
    }

    public function start(Payment $payment, array $input): GatewayStep
    {
        $mobile = preg_replace('/\D+/', '', (string) ($input['mobile'] ?? ''));

        if (strlen((string) $mobile) < 9) {
            throw ValidationException::withMessages(['mobile' => 'Enter a valid mobile number.']);
        }

        $payment->forceFill(['payer' => $mobile, 'reference' => 'DEMO-'.strtoupper(substr($payment->id, -8))])->save();

        return GatewayStep::otp('We sent a code to '.$mobile.'. Demo code: '.config('billing.providers.demo.otp'), strlen((string) config('billing.providers.demo.otp')));
    }

    public function confirm(Payment $payment, array $input): GatewayStep
    {
        return hash_equals((string) config('billing.providers.demo.otp'), trim((string) ($input['otp'] ?? '')))
            ? GatewayStep::paid('Demo payment approved.')
            : GatewayStep::failed('Wrong code.');
    }
}

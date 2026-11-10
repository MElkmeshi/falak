<?php

namespace Falak\Billing\Application\Actions;

use Falak\Billing\Application\Payments\GatewayStep;
use Falak\Billing\Application\Payments\PaymentGateways;
use Falak\Billing\Domain\Enums\PaymentStatus;
use Falak\Billing\Domain\Models\Payment;
use Illuminate\Validation\ValidationException;

final class ConfirmPayment
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly ApplyGatewayStep $apply,
    ) {}

    /**
     * @param  array<string, string>  $input
     */
    public function __invoke(Payment $payment, array $input): Payment
    {
        if ($payment->status !== PaymentStatus::AwaitingOtp) {
            throw ValidationException::withMessages(['otp' => 'This payment is not waiting for a code.']);
        }

        $step = $this->gateways->get($payment->provider)->confirm($payment, $input);

        if ($step->kind === GatewayStep::FAILED) {
            $meta = (array) ($payment->meta ?? []);
            $meta['attempts'] = (int) ($meta['attempts'] ?? 0) + 1;
            $payment->forceFill(['meta' => $meta])->save();

            if ($meta['attempts'] < self::MAX_ATTEMPTS) {
                throw ValidationException::withMessages(['otp' => $step->message.' '.(self::MAX_ATTEMPTS - $meta['attempts']).' tries left.']);
            }
        }

        ($this->apply)($payment, $step);

        return $payment->refresh();
    }
}

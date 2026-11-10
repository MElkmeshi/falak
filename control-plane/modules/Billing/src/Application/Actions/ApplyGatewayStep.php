<?php

namespace Falak\Billing\Application\Actions;

use Falak\Billing\Application\Payments\GatewayStep;
use Falak\Billing\Domain\Enums\PaymentStatus;
use Falak\Billing\Domain\Models\Payment;

final class ApplyGatewayStep
{
    public function __construct(private readonly ActivatePayment $activate) {}

    public function __invoke(Payment $payment, GatewayStep $step): void
    {
        $meta = (array) ($payment->meta ?? []);
        $meta['step'] = $step->toArray();

        match ($step->kind) {
            GatewayStep::OTP => $payment->forceFill(['status' => PaymentStatus::AwaitingOtp, 'meta' => $meta])->save(),
            GatewayStep::REVIEW => $payment->forceFill(['status' => PaymentStatus::AwaitingReview, 'meta' => $meta])->save(),
            GatewayStep::FAILED => $payment->forceFill(['status' => PaymentStatus::Failed, 'failure' => $step->message, 'meta' => $meta])->save(),
            GatewayStep::PAID => ($this->activate)($payment->forceFill(['meta' => $meta])),
            default => $payment->forceFill(['meta' => $meta])->save(),
        };
    }
}

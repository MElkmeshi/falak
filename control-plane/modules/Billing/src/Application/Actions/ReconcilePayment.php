<?php

namespace Falak\Billing\Application\Actions;

use Falak\Billing\Application\Payments\GatewayStep;
use Falak\Billing\Application\Payments\PaymentGateways;
use Falak\Billing\Application\Payments\ReconcilablePaymentGateway;
use Falak\Billing\Domain\Enums\PaymentStatus;
use Falak\Billing\Domain\Models\Payment;
use Falak\Billing\Infrastructure\Payments\MoamalatGateway;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class ReconcilePayment
{
    public const GIVE_UP_AFTER_HOURS = 24;

    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly ApplyGatewayStep $apply,
    ) {}

    public function __invoke(Payment $payment, ?string $networkReference = null): bool
    {
        if ($payment->status === PaymentStatus::Paid) {
            return true;
        }

        $gateway = $this->gateways->find($payment->provider);

        if (! $gateway instanceof ReconcilablePaymentGateway) {
            return false;
        }

        try {
            $step = $gateway instanceof MoamalatGateway ? $gateway->reconcile($payment, $networkReference) : $gateway->reconcile($payment);
        } catch (RuntimeException $e) {
            Log::warning('billing: payment reconciliation failed', ['payment' => $payment->id, 'provider' => $payment->provider, 'error' => $e->getMessage()]);

            return false;
        }

        if ($step?->kind === GatewayStep::PAID) {
            ($this->apply)($payment, $step);

            return true;
        }

        if ($payment->status === PaymentStatus::AwaitingGateway && $payment->created_at?->lt(now()->subHours(self::GIVE_UP_AFTER_HOURS))) {
            ($this->apply)($payment, GatewayStep::failed('The bank never approved this payment.'));
        }

        return false;
    }
}

<?php

namespace Falak\Billing\Application\Actions;

use Falak\Billing\Application\Payments\PaymentGateways;
use Falak\Billing\Domain\Enums\PaymentStatus;
use Falak\Billing\Domain\Models\Payment;
use Falak\Billing\Domain\Models\Plan;
use Illuminate\Validation\ValidationException;

final class StartCheckout
{
    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly ApplyGatewayStep $apply,
    ) {}

    /**
     * @param  array<string, string>  $input
     */
    public function __invoke(string $organizationId, ?string $userId, Plan $plan, string $provider, string $currency, array $input): Payment
    {
        if (! $plan->active) {
            throw ValidationException::withMessages(['plan' => 'This plan is not available.']);
        }

        if (! in_array($currency, (array) config('billing.currencies'), true)) {
            throw ValidationException::withMessages(['currency' => 'Unsupported currency.']);
        }

        $gateway = $this->gateways->get($provider);

        $payment = Payment::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'plan_id' => $plan->id,
            'provider' => $gateway->key(),
            'amount' => $plan->price($currency),
            'currency' => $currency,
            'status' => PaymentStatus::Pending,
        ]);

        try {
            $step = $gateway->start($payment, $input);
        } catch (ValidationException $e) {
            $payment->forceFill(['status' => PaymentStatus::Failed, 'failure' => collect($e->errors())->flatten()->first()])->save();

            throw $e;
        }

        ($this->apply)($payment, $step);

        return $payment->refresh();
    }
}

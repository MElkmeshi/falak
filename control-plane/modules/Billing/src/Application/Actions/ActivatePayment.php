<?php

namespace Falak\Billing\Application\Actions;

use Falak\Billing\Application\Jobs\ProvisionSpace;
use Falak\Billing\Domain\Enums\PaymentStatus;
use Falak\Billing\Domain\Enums\SubscriptionStatus;
use Falak\Billing\Domain\Models\Payment;
use Falak\Billing\Domain\Models\Subscription;
use Falak\Identity\Contracts\AuditLog;
use Illuminate\Support\Facades\DB;

final class ActivatePayment
{
    public function __construct(private readonly AuditLog $audit) {}

    public function __invoke(Payment $payment, ?string $reviewerId = null): void
    {
        $activated = DB::transaction(function () use ($payment, $reviewerId) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === PaymentStatus::Paid) {
                return false;
            }

            $plan = $locked->plan()->firstOrFail();
            $subscription = Subscription::query()->where('organization_id', $locked->organization_id)->lockForUpdate()->latest('ends_at')->first();
            $from = $subscription?->ends_at?->isFuture() && $subscription->plan_id === $plan->id ? $subscription->ends_at : now();

            $subscription ??= new Subscription(['organization_id' => $locked->organization_id]);
            $subscription->fill([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => $subscription->starts_at ?? now(),
                'ends_at' => $from->copy()->addDays($plan->period_days),
            ])->save();

            $locked->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'subscription_id' => $subscription->id,
                'reviewed_by' => $reviewerId,
                'meta' => $payment->meta ?? $locked->meta,
            ])->save();

            return true;
        });

        if (! $activated) {
            return;
        }

        $this->audit->record('billing.payment_paid', 'billing_payment', $payment->id, [
            'provider' => $payment->provider,
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
        ], $payment->organization_id, $reviewerId ?? $payment->user_id);

        ProvisionSpace::dispatch($payment->organization_id, $payment->user_id);
    }
}

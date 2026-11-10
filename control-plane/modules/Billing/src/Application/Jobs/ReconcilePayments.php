<?php

namespace Falak\Billing\Application\Jobs;

use Falak\Billing\Application\Actions\ReconcilePayment;
use Falak\Billing\Domain\Enums\PaymentStatus;
use Falak\Billing\Domain\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class ReconcilePayments implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $uniqueFor = 600;

    public function handle(ReconcilePayment $reconcile): void
    {
        Payment::query()
            ->whereIn('status', [PaymentStatus::AwaitingGateway, PaymentStatus::AwaitingOtp])
            ->whereIn('provider', ['moamalat', 'onepay', 'musrefy_pay', 'yussor_online', 'sahara_pay'])
            ->where('created_at', '<', now()->subMinutes(3))
            ->where('created_at', '>', now()->subHours(ReconcilePayment::GIVE_UP_AFTER_HOURS + 1))
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->each(fn (Payment $payment) => $reconcile($payment));
    }
}

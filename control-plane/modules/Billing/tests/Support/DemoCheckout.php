<?php

namespace Falak\Billing\Tests\Support;

use Falak\Billing\Domain\Models\Payment;

final class DemoCheckout
{
    public static function start(object $test): Payment
    {
        $location = $test->post('/billing/checkout', [
            'plan_id' => $test->plan->id,
            'provider' => 'demo',
            'currency' => 'LYD',
            'fields' => ['mobile' => '0912345678'],
        ])->assertRedirect()->headers->get('Location');

        return Payment::query()->findOrFail(basename((string) $location));
    }
}

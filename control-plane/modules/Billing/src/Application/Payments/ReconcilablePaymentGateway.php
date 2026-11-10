<?php

namespace Falak\Billing\Application\Payments;

use Falak\Billing\Domain\Models\Payment;

interface ReconcilablePaymentGateway extends PaymentGateway
{
    /**
     * Ask the provider what became of a payment the customer never finished in the panel.
     * Null means the provider does not know yet; ask again later.
     */
    public function reconcile(Payment $payment): ?GatewayStep;
}

<?php

namespace Falak\Billing\Infrastructure\Payments;

use Falak\Billing\Application\Payments\GatewayStep;
use Falak\Billing\Application\Payments\ReconcilablePaymentGateway;
use Falak\Billing\Domain\Models\Payment;

final class MoamalatGateway implements ReconcilablePaymentGateway
{
    public function key(): string
    {
        return 'moamalat';
    }

    public function label(): string
    {
        return (string) config('billing.providers.moamalat.label');
    }

    public function enabled(): bool
    {
        $cfg = (array) config('billing.providers.moamalat');

        return ($cfg['enabled'] ?? false) && ! empty($cfg['merchant_id']) && ! empty($cfg['terminal_id']) && ! empty($cfg['secret_key']);
    }

    public function currencies(): array
    {
        return ['LYD'];
    }

    public function startFields(): array
    {
        return [];
    }

    public function start(Payment $payment, array $input): GatewayStep
    {
        return GatewayStep::redirect('Enter your card details in the Moamalat window. Your plan starts as soon as the bank approves the payment.');
    }

    public function confirm(Payment $payment, array $input): GatewayStep
    {
        return GatewayStep::failed('Card payments are confirmed by the bank, not with a code.');
    }

    public function reconcile(Payment $payment, ?string $networkReference = null): ?GatewayStep
    {
        $approved = null;

        foreach (MoamalatClient::fromConfig()->transactions($payment->id, $networkReference) as $transaction) {
            if ($this->isApprovedFor($transaction, $payment)) {
                $approved = $transaction;
                break;
            }
        }

        if ($approved === null) {
            return null;
        }

        $payment->forceFill(array_filter([
            'reference' => $this->referenceOf($approved),
            'payer' => is_string($approved['CardNo'] ?? null) && $approved['CardNo'] !== '' ? $approved['CardNo'] : null,
        ]))->save();

        return GatewayStep::paid('The bank approved the card payment.');
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function referenceOf(array $transaction): ?string
    {
        foreach (['NetworkReference', 'RRN', 'TransactionId'] as $field) {
            if (is_scalar($transaction[$field] ?? null) && (string) $transaction[$field] !== '') {
                return (string) $transaction[$field];
            }
        }

        return null;
    }

    /**
     * Moamalat reports AmountTrxn in dirhams as a string ("280000" for 280 LYD), so only an exact
     * dirham match counts: comparing in dinars would let a 50-dirham payment pass for 50 LYD.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function isApprovedFor(array $transaction, Payment $payment): bool
    {
        $approved = ($transaction['Status'] ?? null) === 'Approved' || ($transaction['ResCodeDesc'] ?? null) === 'Approved';
        $sale = ! filter_var($transaction['IsRefund'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $reference = (string) ($transaction['MerchantReference'] ?? '') === $payment->id;
        $amount = $transaction['AmountTrxn'] ?? null;
        $paid = is_numeric($amount) && (int) $amount === MoamalatClient::toDirhams((string) $payment->amount);

        return $approved && $sale && $reference && $paid;
    }
}

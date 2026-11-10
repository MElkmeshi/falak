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
            'reference' => is_scalar($approved['NetworkReference'] ?? null) ? (string) $approved['NetworkReference'] : null,
            'payer' => is_string($approved['CardNo'] ?? null) && $approved['CardNo'] !== '' ? $approved['CardNo'] : null,
        ]))->save();

        return GatewayStep::paid('The bank approved the card payment.');
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function isApprovedFor(array $transaction, Payment $payment): bool
    {
        $approved = ($transaction['Status'] ?? null) === 'Approved' || ($transaction['ResCodeDesc'] ?? null) === 'Approved';
        $reference = (string) ($transaction['MerchantReference'] ?? '') === $payment->id;
        $amount = $transaction['AmountTrxn'] ?? null;
        $paid = is_numeric($amount) && (
            abs((float) $amount - (float) $payment->amount) < 0.001
            || (int) round((float) $amount) === MoamalatClient::toDirhams((string) $payment->amount)
        );

        return $approved && $reference && $paid;
    }
}

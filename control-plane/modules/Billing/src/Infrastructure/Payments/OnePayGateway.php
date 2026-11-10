<?php

namespace Falak\Billing\Infrastructure\Payments;

use Falak\Billing\Application\Payments\GatewayStep;
use Falak\Billing\Application\Payments\ReconcilablePaymentGateway;
use Falak\Billing\Domain\Models\Payment;
use Falak\Kernel\Security\Sealer;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class OnePayGateway implements ReconcilablePaymentGateway
{
    public const WALLETS = [
        'onepay' => ['label' => 'OnePay wallet', 'card_prefix' => null],
        'musrefy_pay' => ['label' => 'Musrefy Pay', 'card_prefix' => '11'],
        'yussor_online' => ['label' => 'Yusr Online', 'card_prefix' => '33'],
        'sahara_pay' => ['label' => 'Sahara Pay', 'card_prefix' => '66'],
    ];

    public function __construct(private readonly string $wallet) {}

    private static function sessionAad(Payment $payment): string
    {
        return "billing_payment:{$payment->id}:onepay_session";
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return array_map(fn (string $wallet) => new self($wallet), array_keys(self::WALLETS));
    }

    public function key(): string
    {
        return $this->wallet;
    }

    public function label(): string
    {
        return self::WALLETS[$this->wallet]['label'];
    }

    public function enabled(): bool
    {
        $cfg = $this->config();

        return ($cfg['enabled'] ?? false)
            && in_array($this->wallet, (array) ($cfg['wallets'] ?? []), true)
            && ! empty($cfg['base_url']) && ! empty($cfg['user_id']) && ! empty($cfg['pin']) && ! empty($cfg['provider_id']);
    }

    public function currencies(): array
    {
        return ['LYD'];
    }

    public function startFields(): array
    {
        return [['name' => 'identity_card', 'label' => $this->label().' card number', 'type' => 'text', 'placeholder' => 'The number on your wallet card']];
    }

    public function start(Payment $payment, array $input): GatewayStep
    {
        $card = $this->normalizeCard((string) ($input['identity_card'] ?? ''));

        try {
            $session = $this->client()->openSession($card, (float) $payment->amount, $payment->id);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['identity_card' => $e->getMessage()]);
        }

        $payment->forceFill([
            'payer' => $card,
            'meta' => [...(array) ($payment->meta ?? []), 'session' => app(Sealer::class)->seal($session, self::sessionAad($payment), $payment->organization_id)],
        ])->save();

        return GatewayStep::otp('Enter the code '.$this->label().' sent to the mobile number on your wallet.', 6);
    }

    public function confirm(Payment $payment, array $input): GatewayStep
    {
        try {
            $session = app(Sealer::class)->open((string) ($payment->meta['session'] ?? ''), self::sessionAad($payment), $payment->organization_id);
        } catch (RuntimeException) {
            return GatewayStep::failed('Start the payment again.');
        }

        try {
            $result = $this->client()->completeSession($session, trim((string) ($input['otp'] ?? '')));
        } catch (RuntimeException $e) {
            return GatewayStep::failed($e->getMessage());
        }

        if (! $result['success']) {
            return GatewayStep::failed($result['message'] ?? 'The wallet did not accept this code.');
        }

        if ($result['trace_id'] !== null) {
            $payment->forceFill(['reference' => $result['trace_id']])->save();
        }

        return GatewayStep::paid($this->label().' payment confirmed.');
    }

    public function reconcile(Payment $payment): ?GatewayStep
    {
        $transactions = $this->client()->transactions($payment->id, $payment->created_at ?? now());

        foreach ($transactions as $transaction) {
            if (is_numeric($transaction['amount'] ?? null) && abs((float) $transaction['amount'] - (float) $payment->amount) < 0.001) {
                return GatewayStep::paid($this->label().' payment found in the wallet report.');
            }
        }

        return null;
    }

    private function normalizeCard(string $card): string
    {
        $card = preg_replace('/\s+/', '', $card) ?? '';

        if (! preg_match('/^\d{7,16}$/', $card)) {
            throw ValidationException::withMessages(['identity_card' => 'Enter the number printed on your wallet card.']);
        }

        $prefix = self::WALLETS[$this->wallet]['card_prefix'];

        return strlen($card) === 7 && $prefix !== null ? $prefix.$card : $card;
    }

    private function client(): OnePayClient
    {
        return new OnePayClient($this->config());
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return (array) config('billing.providers.onepay');
    }
}

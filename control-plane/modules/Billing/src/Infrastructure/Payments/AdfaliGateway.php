<?php

namespace Falak\Billing\Infrastructure\Payments;

use Falak\Billing\Application\Payments\GatewayStep;
use Falak\Billing\Application\Payments\PaymentGateway;
use Falak\Billing\Domain\Models\Payment;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AdfaliGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'adfali';
    }

    public function label(): string
    {
        return (string) config('billing.providers.adfali.label');
    }

    public function enabled(): bool
    {
        $cfg = (array) config('billing.providers.adfali');

        return ($cfg['enabled'] ?? false) && ! empty($cfg['base_url']) && ! empty($cfg['merchant_mobile']);
    }

    public function startFields(): array
    {
        return [['name' => 'mobile', 'label' => 'Libyana mobile number', 'type' => 'tel', 'placeholder' => '092XXXXXXX']];
    }

    public function start(Payment $payment, array $input): GatewayStep
    {
        $mobile = $this->normalize((string) ($input['mobile'] ?? ''));

        try {
            $session = $this->client()->initiate($mobile, (float) $payment->amount);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['mobile' => $e->getMessage()]);
        }

        $payment->forceFill(['payer' => $mobile, 'meta' => ['session' => $session]])->save();

        return GatewayStep::otp('Enter the PIN Libyana sent to '.$mobile.' by SMS.', 4);
    }

    public function confirm(Payment $payment, array $input): GatewayStep
    {
        $session = (string) ($payment->meta['session'] ?? '');

        if ($session === '') {
            return GatewayStep::failed('Start the payment again.');
        }

        try {
            return $this->client()->confirm($session, trim((string) ($input['otp'] ?? '')))
                ? GatewayStep::paid('Adfali payment confirmed.')
                : GatewayStep::failed('Adfali did not confirm the payment.');
        } catch (RuntimeException $e) {
            return GatewayStep::failed($e->getMessage());
        }
    }

    private function normalize(string $mobile): string
    {
        $digits = ltrim(preg_replace('/[^\d+]/', '', $mobile) ?? '', '0');

        if (! preg_match('/^(\+?218)?9[1-5]\d{7}$/', $digits)) {
            throw ValidationException::withMessages(['mobile' => 'Enter a Libyan mobile number like 092XXXXXXX.']);
        }

        return str_starts_with($digits, '+218') ? $digits : (str_starts_with($digits, '218') ? '+'.$digits : '+218'.$digits);
    }

    private function client(): AdfaliClient
    {
        $cfg = (array) config('billing.providers.adfali');

        return new AdfaliClient((string) $cfg['base_url'], (string) $cfg['merchant_mobile'], (string) $cfg['merchant_pin'], (string) $cfg['service_password']);
    }
}

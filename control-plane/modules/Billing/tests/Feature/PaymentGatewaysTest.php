<?php

use App\Http\Middleware\HandleInertiaRequests;
use Falak\Billing\Application\Actions\ReconcilePayment;
use Falak\Billing\Application\Jobs\ProvisionSpace;
use Falak\Billing\Application\Jobs\ReconcilePayments;
use Falak\Billing\Domain\Enums\PaymentStatus;
use Falak\Billing\Domain\Models\Payment;
use Falak\Billing\Domain\Models\Plan;
use Falak\Billing\Infrastructure\Payments\MoamalatClient;
use Falak\Identity\Contracts\Role;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const MOAMALAT_KEY = '3a488a89b3f7993476c252f017c488bb';
const MOAMALAT_NOTIFY_KEY = '6f1e3a2b4c5d6e7f8091a2b3c4d5e6f7';

beforeEach(function () {
    Queue::fake();
    [$this->user, $this->organization] = actingAsMember(Role::Owner);
    $this->plan = Plan::query()->where('slug', 'pro')->firstOrFail();

    config([
        'billing.providers.adfali' => [...config('billing.providers.adfali'), 'enabled' => true, 'merchant_mobile' => '218920000000', 'merchant_pin' => '1111', 'service_password' => 'svc'],
        'billing.providers.onepay' => [...config('billing.providers.onepay'), 'enabled' => true, 'base_url' => 'https://onepay.test/Yusr', 'user_id' => '7', 'pin' => '9999', 'provider_id' => '3', 'wallets' => ['onepay', 'musrefy_pay']],
        'billing.providers.moamalat' => [...config('billing.providers.moamalat'), 'enabled' => true, 'merchant_id' => '10081014649', 'terminal_id' => '99179395', 'secret_key' => MOAMALAT_KEY, 'notification_key' => MOAMALAT_NOTIFY_KEY],
    ]);
});

function checkout(object $test, string $provider, array $fields = [], string $currency = 'LYD')
{
    return $test->post('/billing/checkout', ['plan_id' => $test->plan->id, 'provider' => $provider, 'currency' => $currency, 'fields' => $fields]);
}

function soap(string $tag, string $value): string
{
    return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
        ."<{$tag}Response xmlns=\"http://tempuri.org/\"><{$tag}Result>{$value}</{$tag}Result></{$tag}Response></soap:Body></soap:Envelope>";
}

function moamalatTransactions(array ...$transactions): array
{
    return ['Message' => null, 'Success' => true, 'TotalCountAllTransaction' => count($transactions), 'Transactions' => [['DateTransactions' => $transactions]]];
}

it('offers each Libyan gateway only in dinars', function () {
    $this->get('/billing')->assertInertia(fn ($page) => $page
        ->where('gateways', fn ($gateways) => collect($gateways)->whereIn('key', ['adfali', 'onepay', 'musrefy_pay', 'moamalat'])->every(fn ($g) => $g['currencies'] === ['LYD'])
            && collect($gateways)->pluck('key')->doesntContain('sahara_pay')));

    checkout($this, 'adfali', ['mobile' => '0921234567'], 'EGP')->assertSessionHasErrors('provider');
});

it('charges an Edfali wallet with the PIN Libyana sends', function () {
    Http::fake([
        '*Edfali.asmx' => Http::sequence()->push(soap('DoPTrans', 'SESSION-42'))->push(soap('OnlineConfTrans', 'OK')),
    ]);

    checkout($this, 'adfali', ['mobile' => '0921234567'])->assertRedirect();
    $payment = Payment::query()->latest()->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::AwaitingOtp)->and($payment->payer)->toBe('+218921234567')->and($payment->meta['step']['otp_length'])->toBe(4);
    Http::assertSent(fn (Request $r) => str_contains($r->body(), '<Cmobile>+218921234567</Cmobile>') && str_contains($r->body(), '<Amount>50</Amount>') && $r->header('SOAPAction')[0] === 'http://tempuri.org/DoPTrans');

    $this->post("/billing/payments/{$payment->id}/confirm", ['otp' => '4321'])->assertRedirect();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
    Http::assertSent(fn (Request $r) => str_contains($r->body(), '<sessionID>SESSION-42</sessionID>') && str_contains($r->body(), '<Pin>4321</Pin>'));
    Queue::assertPushed(ProvisionSpace::class);
});

it('shows the Edfali reason when the wallet cannot pay', function () {
    Http::fake(['*Edfali.asmx' => Http::response(soap('DoPTrans', 'BAL'))]);

    checkout($this, 'adfali', ['mobile' => '0921234567'])->assertSessionHasErrors(['mobile' => 'Not enough balance in the Edfali account.']);

    expect(Payment::query()->latest()->firstOrFail()->status)->toBe(PaymentStatus::Failed);
});

it('escapes customer input inside the Edfali SOAP envelope', function () {
    Http::fake(['*Edfali.asmx' => Http::sequence()->push(soap('DoPTrans', 'S1'))->push(soap('OnlineConfTrans', 'NO'))]);

    checkout($this, 'adfali', ['mobile' => '0921234567']);
    $payment = Payment::query()->latest()->firstOrFail();
    $this->post("/billing/payments/{$payment->id}/confirm", ['otp' => '</Pin><Pin>1'])->assertSessionHasErrors('otp');

    Http::assertSent(fn (Request $r) => str_contains($r->body(), '&lt;/Pin&gt;&lt;Pin&gt;1'));
    expect($payment->refresh()->status)->toBe(PaymentStatus::AwaitingOtp);
});

it('charges a OnePay wallet card in two steps and keeps the trace id', function () {
    Http::fake([
        'onepay.test/Yusr/api/OnlinePaymentServices/Signin' => Http::response(['type' => 1, 'content' => ['value' => 'MERCHANT-TOKEN', 'validTo' => '2026-12-01']]),
        'onepay.test/Yusr/api/OnlinePaymentServices/OpenSession' => Http::response(['type' => 1, 'content' => ['value' => 'SESSION-TOKEN', 'validTo' => '2026-12-01']]),
        'onepay.test/Yusr/api/OnlinePaymentServices/CompleteSession' => Http::response(['type' => 1, 'traceId' => 'TR-991', 'messages' => []]),
    ]);

    checkout($this, 'musrefy_pay', ['identity_card' => '1234567'])->assertRedirect();
    $payment = Payment::query()->latest()->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::AwaitingOtp)
        ->and($payment->payer)->toBe('111234567')
        ->and($payment->meta['session'])->not->toContain('SESSION-TOKEN');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'OpenSession') && $r['identityCard'] === '111234567' && $r['transactionId'] === $payment->id
        && (float) $r['amount'] === 50.0 && $r->header('Authorization')[0] === 'Bearer MERCHANT-TOKEN');

    $this->post("/billing/payments/{$payment->id}/confirm", ['otp' => '654321']);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)->and($payment->reference)->toBe('TR-991');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'CompleteSession') && $r['otp'] === '654321' && $r->header('Authorization')[0] === 'Bearer SESSION-TOKEN');
});

it('shows the OnePay message when the code is wrong', function () {
    Http::fake([
        '*Signin' => Http::response(['type' => 1, 'content' => ['value' => 'T', 'validTo' => 'x']]),
        '*OpenSession' => Http::response(['type' => 1, 'content' => ['value' => 'S', 'validTo' => 'x']]),
        '*CompleteSession' => Http::response(['type' => 2, 'messages' => ['رمز التحقق غير صحيح']]),
    ]);

    checkout($this, 'onepay', ['identity_card' => '331234567']);
    $payment = Payment::query()->latest()->firstOrFail();

    $this->post("/billing/payments/{$payment->id}/confirm", ['otp' => '000000'])->assertSessionHasErrors(['otp' => 'رمز التحقق غير صحيح 4 tries left.']);
});

it('finds a OnePay payment the customer never confirmed in the panel', function () {
    Http::fake([
        '*Signin' => Http::response(['type' => 1, 'content' => ['value' => 'T', 'validTo' => 'x']]),
        '*OpenSession' => Http::response(['type' => 1, 'content' => ['value' => 'S', 'validTo' => 'x']]),
        '*AccountReport' => Http::response(['type' => 1, 'content' => ['transactionList' => [['amount' => 50.0, 'providerTransactionId' => 'x']]]]),
    ]);

    checkout($this, 'onepay', ['identity_card' => '331234567']);
    $payment = Payment::query()->latest()->firstOrFail();
    $payment->forceFill(['created_at' => now()->subMinutes(10)])->save();

    (new ReconcilePayments)->handle(app(ReconcilePayment::class));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
});

it('signs the Moamalat window on the server with the plan price', function () {
    checkout($this, 'moamalat')->assertRedirect();
    $payment = Payment::query()->latest()->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::AwaitingGateway);

    $config = $this->getJson("/billing/payments/{$payment->id}/moamalat")->assertOk()
        ->assertJsonPath('script', 'https://tnpg.moamalat.net:6006/js/lightbox.js')
        ->json('config');

    $expected = hash_hmac('sha256', "Amount=50000&DateTimeLocalTrxn={$config['TrxDateTime']}&MerchantId=10081014649&MerchantReference={$payment->id}&TerminalId=99179395", hex2bin(MOAMALAT_KEY));

    expect($config['AmountTrxn'])->toBe(50000)->and($config['MerchantReference'])->toBe($payment->id)->and($config['SecureHash'])->toBe($expected);
});

it('activates a Moamalat payment only after the bank approves the full amount', function () {
    checkout($this, 'moamalat');
    $payment = Payment::query()->latest()->firstOrFail();

    Http::fake(['tnpg.moamalat.net/*' => Http::sequence()
        ->push(moamalatTransactions(['Status' => 'Approved', 'MerchantReference' => $payment->id, 'AmountTrxn' => '50', 'RRN' => '631400000001']))
        ->push(moamalatTransactions(['Status' => 'Approved', 'ResCodeDesc' => 'Approved', 'MerchantReference' => $payment->id, 'AmountTrxn' => '50000', 'RRN' => '631400000002', 'CardNo' => '639499XXXXXX1234', 'IsRefund' => false])),
    ]);

    $this->post("/billing/payments/{$payment->id}/moamalat", ['network_reference' => 'N1'])->assertRedirect();
    expect($payment->refresh()->status)->toBe(PaymentStatus::AwaitingGateway);

    $this->post("/billing/payments/{$payment->id}/moamalat", ['network_reference' => 'N2'])->assertRedirect();
    expect($payment->refresh())->status->toBe(PaymentStatus::Paid)->reference->toBe('631400000002')->payer->toBe('639499XXXXXX1234');
    Http::assertSent(fn (Request $r) => $r['MerchantReference'] === $payment->id && $r['NetworkReference'] === 'N2');
});

it('trusts a Moamalat notification only with a valid signature and still asks the bank', function () {
    checkout($this, 'moamalat');
    $payment = Payment::query()->latest()->firstOrFail();
    Http::fake(['tnpg.moamalat.net/*' => Http::response(moamalatTransactions(['ResCodeDesc' => 'Approved', 'MerchantReference' => $payment->id, 'AmountTrxn' => 50000]))]);

    $notification = ['MerchantId' => '10081014649', 'TerminalId' => '99179395', 'Amount' => '50000', 'Currency' => '434', 'DateTimeLocalTrxn' => '261110120000',
        'MerchantReference' => $payment->id, 'NetworkReference' => 'N9', 'ActionCode' => '00', 'TxnType' => '1'];
    $sign = fn (array $n) => strtoupper(hash_hmac('sha256', "Amount={$n['Amount']}&Currency={$n['Currency']}&DateTimeLocalTrxn={$n['DateTimeLocalTrxn']}&MerchantId={$n['MerchantId']}&TerminalId={$n['TerminalId']}", hex2bin(MOAMALAT_NOTIFY_KEY)));

    $this->postJson('/api/webhooks/billing/moamalat', [...$notification, 'SecureHash' => str_repeat('0', 64)])->assertUnauthorized();
    expect($payment->refresh()->status)->toBe(PaymentStatus::AwaitingGateway);
    Http::assertNothingSent();

    $this->postJson('/api/webhooks/billing/moamalat', [...$notification, 'SecureHash' => $sign($notification)])->assertOk()->assertJsonPath('Success', true);
    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
});

it('gives up on a Moamalat payment the bank never approved', function () {
    checkout($this, 'moamalat');
    $payment = Payment::query()->latest()->firstOrFail();
    $payment->forceFill(['created_at' => now()->subHours(24)->subMinutes(5)])->save();
    Http::fake(['tnpg.moamalat.net/*' => Http::response(moamalatTransactions())]);

    (new ReconcilePayments)->handle(app(ReconcilePayment::class));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed);
});

it('lets the Moamalat window through the content security policy', function () {
    config(['kernel.csp.script_src' => [app(MoamalatClient::class, ['cfg' => ['production' => true]])->lightboxOrigin()], 'kernel.csp.frame_src' => ['https://npg.moamalat.net:6006']]);

    expect(HandleInertiaRequests::contentSecurityPolicy('n'))
        ->toContain("script-src 'self' 'nonce-n' https://npg.moamalat.net:6006")
        ->toContain("frame-src 'self' https://npg.moamalat.net:6006");
});

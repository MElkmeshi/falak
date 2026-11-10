<?php

use Falak\Billing\Application\Jobs\SyncSpaces;
use Falak\Billing\Domain\Enums\PaymentStatus;
use Falak\Billing\Domain\Enums\SpaceStatus;
use Falak\Billing\Domain\Enums\SubscriptionStatus;
use Falak\Billing\Domain\Models\Payment;
use Falak\Billing\Domain\Models\Plan;
use Falak\Billing\Domain\Models\Space;
use Falak\Billing\Domain\Models\Subscription;
use Falak\Billing\Infrastructure\IncusClient;
use Falak\Billing\Infrastructure\SpaceRouter;
use Falak\Billing\Tests\Support\DemoCheckout;
use Falak\Billing\Tests\Support\FakeIncus;
use Falak\Billing\Tests\Support\FakeTemplates;
use Falak\Identity\Contracts\Role;
use Falak\Servers\Contracts\ServerDirectory;
use Falak\Servers\Domain\Models\Server;
use Falak\Servers\Events\ServerProvisioned;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::fake(['*' => Http::response(['ok' => true])]);
    $this->incus = FakeIncus::install();
    [$this->user, $this->organization] = actingAsMember(Role::Owner);
    $this->plan = Plan::query()->where('slug', 'pro')->firstOrFail();
});

it('shows the plans on sale', function () {
    $this->get('/billing')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Billing/Customer/Plan', false)
        ->has('plans', 3)
        ->where('subscription', null)
        ->where('gateways.0.key', 'demo'));
});

it('pays with the demo wallet and provisions a space with the plan limits', function () {
    $payment = DemoCheckout::start($this);

    expect($payment->status)->toBe(PaymentStatus::AwaitingOtp)
        ->and($payment->amount)->toBe('50.00')
        ->and($payment->currency)->toBe('LYD');

    $this->post("/billing/payments/{$payment->id}/confirm", ['otp' => '000000'])->assertSessionHasErrors('otp');
    expect($payment->refresh()->status)->toBe(PaymentStatus::AwaitingOtp);

    $this->post("/billing/payments/{$payment->id}/confirm", ['otp' => '123456'])->assertRedirect("/billing/payments/{$payment->id}");

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);

    $subscription = Subscription::currentFor($this->organization->id);
    expect($subscription->isActive())->toBeTrue()
        ->and($subscription->plan_id)->toBe($this->plan->id)
        ->and((int) round(now()->diffInDays($subscription->ends_at)))->toBe(30);

    $space = Space::query()->where('organization_id', $this->organization->id)->firstOrFail();
    $server = Server::query()->findOrFail($space->server_id);

    expect($server->organization_id)->toBe($this->organization->id)
        ->and($space->status)->toBe(SpaceStatus::Enrolling)
        ->and($space->ipv4)->toBe('10.50.0.10')
        ->and($this->incus->instances[$space->instance])->toMatchArray([
            'status' => 'Running',
            'cpus' => 1.0,
            'memory_mb' => 2048,
            'disk_gb' => 15,
        ])
        ->and($this->incus->instances[$space->instance]['install_url'])->toContain('/install/');
});

it('extends the plan when the same plan is paid again', function () {
    $this->post('/billing/payments/'.DemoCheckout::start($this)->id.'/confirm', ['otp' => '123456']);
    $this->post('/billing/payments/'.DemoCheckout::start($this)->id.'/confirm', ['otp' => '123456']);

    $subscription = Subscription::currentFor($this->organization->id);
    expect((int) round(now()->diffInDays($subscription->ends_at)))->toBe(60)
        ->and(Space::query()->count())->toBe(1)
        ->and(Subscription::query()->count())->toBe(1);
});

it('keeps manual payments for review and only the operator approves them', function () {
    $this->post('/billing/checkout', [
        'plan_id' => $this->plan->id, 'provider' => 'manual', 'currency' => 'EGP',
        'fields' => ['method' => 'InstaPay', 'reference' => 'IP-778899'],
    ])->assertRedirect();

    $payment = Payment::query()->latest()->firstOrFail();
    expect($payment->status)->toBe(PaymentStatus::AwaitingReview)
        ->and($payment->amount)->toBe('500.00')
        ->and($payment->reference)->toBe('IP-778899');

    $this->post("/billing/admin/payments/{$payment->id}/approve")->assertNotFound();

    [$operator, $operatorOrg] = actingAsMember(Role::Owner);
    config(['billing.operator_organization' => $operatorOrg->id]);

    $this->get('/billing/admin')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Billing/admin/Index', false)->where('pending_reviews', 1));
    $this->post("/billing/admin/payments/{$payment->id}/approve")->assertRedirect();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and(Space::query()->where('organization_id', $this->organization->id)->exists())->toBeTrue();
});

it('does not let viewers buy plans', function () {
    [, $org] = [$this->user, $this->organization];
    actingAsMember(Role::Viewer, $org);

    $this->post('/billing/checkout', ['plan_id' => $this->plan->id, 'provider' => 'demo', 'currency' => 'LYD', 'fields' => ['mobile' => '0912345678']])
        ->assertForbidden();
});

it('suspends the space when the plan expires', function () {
    $this->post('/billing/payments/'.DemoCheckout::start($this)->id.'/confirm', ['otp' => '123456']);
    $space = Space::query()->firstOrFail();

    Subscription::query()->update(['ends_at' => now()->subMinute()]);
    (new SyncSpaces)->handle(app(IncusClient::class), app(ServerDirectory::class), app(SpaceRouter::class));

    expect(Subscription::query()->first()->status)->toBe(SubscriptionStatus::Expired)
        ->and($space->refresh()->status)->toBe(SpaceStatus::Suspended)
        ->and($this->incus->instances[$space->instance]['status'])->toBe('Stopped');
});

it('routes each host to its space over layer 4', function () {
    $config = app(SpaceRouter::class)->config(['n8n.example.test' => '10.50.0.10', 'blog.example.test' => '10.50.0.11']);
    $https = $config['apps']['layer4']['servers']['https']['routes'][0]['handle'];

    expect($https[0]['handler'])->toBe('proxy_protocol')
        ->and($https[1]['routes'])->toHaveCount(2)
        ->and($https[1]['routes'][0]['match'][0]['tls']['sni'])->toBe(['n8n.example.test'])
        ->and($https[1]['routes'][0]['handle'][0]['upstreams'][0]['dial'])->toBe(['10.50.0.10:443'])
        ->and($config['apps']['layer4']['servers']['http']['routes'][0]['handle'][1]['routes'][1]['handle'][0]['upstreams'][0]['dial'])->toBe(['10.50.0.11:80']);
});

it('refuses one-click apps before the space is ready', function () {
    $this->post('/billing/payments/'.DemoCheckout::start($this)->id.'/confirm', ['otp' => '123456']);

    $this->post('/billing/apps/n8n')->assertSessionHasErrors('app');
});

it('only deploys one-click apps that fit in the plan memory', function () {
    $templates = FakeTemplates::install();
    $this->plan = Plan::query()->where('slug', 'starter')->firstOrFail();
    $this->post('/billing/payments/'.DemoCheckout::start($this)->id.'/confirm', ['otp' => '123456']);
    Space::query()->where('organization_id', $this->organization->id)->update(['status' => SpaceStatus::Ready, 'server_id' => Server::factory()->create(['organization_id' => $this->organization->id])->id]);

    $this->get('/store')->assertInertia(fn (Assert $page) => $page
        ->component('Billing/Customer/Store', false)
        ->where('store.0.fits', false)
        ->where('store.0.needs_plan', 'Pro')
        ->where('store.1.fits', true));

    $this->post('/billing/apps/n8n')->assertSessionHasErrors('app');
    $this->post('/billing/apps/uptime-kuma')->assertSessionHasNoErrors();

    expect($templates->deployed)->toBe(['uptime-kuma']);
});

it('marks the space ready as soon as its server finishes provisioning', function () {
    Queue::fake();

    ServerProvisioned::dispatch('server', $this->organization->id, 'app', 'My space');

    Queue::assertPushed(SyncSpaces::class);
});

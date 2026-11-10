<?php

use Falak\Billing\Domain\Enums\SpaceStatus;
use Falak\Billing\Domain\Models\Plan;
use Falak\Billing\Domain\Models\Space;
use Falak\Billing\Tests\Support\FakeIncus;
use Falak\Billing\Tests\Support\FakeTemplates;
use Falak\Identity\Contracts\Role;
use Falak\Identity\Domain\Models\Organization;
use Falak\Servers\Domain\Models\Server;
use Falak\Sites\Contracts\BuildMode;
use Falak\Sites\Contracts\Data\LaravelSettings;
use Falak\Sites\Contracts\Framework;
use Falak\Sites\Contracts\SiteRuntime;
use Falak\Sites\Domain\Models\Site;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Http::fake(['*' => Http::response(['ok' => true])]);
    FakeIncus::install();
    FakeTemplates::install();
    config(['billing.operator_organization' => Organization::factory()->create()->id]);
    [$this->user, $this->organization] = actingAsMember(Role::Owner);
});

it('keeps customers out of the developer pages', function (string $path) {
    $this->get($path)->assertRedirect('/home');
})->with(['/projects', '/servers', '/settings/profile', '/settings/members', '/templates']);

it('refuses developer actions from customers, such as adding a server', function () {
    $this->post('/servers', ['name' => 'mine', 'provider' => 'custom'])->assertForbidden();
});

it('lets customers use their own area', function (string $path) {
    $this->get($path)->assertOk();
})->with(['/home', '/apps', '/store', '/billing', '/account']);

it('lets customers update their profile from the account page', function () {
    $this->patch('/settings/profile', ['name' => 'Renamed Student', 'email' => $this->user->email])->assertSessionHasNoErrors();

    expect($this->user->refresh()->name)->toBe('Renamed Student');
});

it('leaves the operator organization with the full dashboard', function () {
    config(['billing.operator_organization' => $this->organization->id]);

    $this->get('/projects')->assertOk();
});

it('greets a customer without a plan with the plans', function () {
    $this->get('/home')->assertInertia(fn (Assert $page) => $page
        ->component('Billing/Customer/Home', false)
        ->where('subscription', null)
        ->has('plans', 3));
});

it('shows the apps of the customer with a plain status', function () {
    $this->plan = Plan::query()->where('slug', 'pro')->firstOrFail();
    $this->post('/billing/payments/'.billing_pay_demo($this)->id.'/confirm', ['otp' => '123456']);
    Space::query()->update(['status' => SpaceStatus::Ready, 'server_id' => Server::factory()->create(['organization_id' => $this->organization->id])->id]);

    $this->get('/apps')->assertInertia(fn (Assert $page) => $page
        ->component('Billing/Customer/Apps', false)
        ->where('space.status', 'ready')
        ->has('apps', 0));
});

it('does not restart or delete apps of another organization', function () {
    $site = Site::query()->create(['organization_id' => Organization::factory()->create()->id, 'name' => 'Theirs', 'slug' => 'theirs',
        'runtime' => SiteRuntime::Compose, 'build_mode' => BuildMode::Native, 'framework' => Framework::Laravel, 'web_directory' => 'public',
        'unix_user' => 'falak', 'isolated' => false, 'deploy_script' => '', 'laravel' => new LaravelSettings]);

    $this->post("/apps/{$site->id}/restart")->assertNotFound();
    $this->delete("/apps/{$site->id}")->assertNotFound();

    expect(Site::query()->find($site->id))->not->toBeNull();
});

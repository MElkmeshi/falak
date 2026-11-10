<?php

use Falak\Billing\Domain\Models\Plan;
use Falak\Billing\Tests\Support\FakeTemplates;
use Falak\Identity\Contracts\Role;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    FakeTemplates::install();
    config(['billing.operator_organization' => 'operator-org']);
});

it('shows plans and one-click apps to visitors without logging in', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Billing/Landing', false)
            ->has('plans', 3)
            ->where('plans.0.slug', 'starter')
            ->where('plans.0.runs', ['Uptime Kuma'])
            ->where('plans.1.runs', ['n8n', 'Uptime Kuma'])
            ->has('apps', 2)
            ->where('payment_methods', fn ($methods) => ! collect($methods)->contains('Demo wallet')));
});

it('hides inactive plans from the landing page', function () {
    Plan::query()->where('slug', 'business')->update(['active' => false]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page->has('plans', 2));
});

it('sends a visitor who picks a plan through sign-up and on to checkout for that plan', function () {
    $this->get('/start/pro')->assertRedirect(route('register'));

    $this->post('/register', [
        'name' => 'New Student',
        'email' => 'new.student@hajat.com.ly',
        'password' => 'Test-pass-12345!',
        'password_confirmation' => 'Test-pass-12345!',
    ])->assertRedirect('/billing?plan=pro');

    $this->get('/billing?plan=pro')->assertInertia(fn (Assert $page) => $page->component('Billing/Customer/Plan', false)->where('selected_plan', 'pro'));
});

it('sends a visitor who clicks get started to the plans after sign-up', function () {
    $this->get('/start')->assertRedirect(route('register'));

    $this->post('/register', [
        'name' => 'Another Student',
        'email' => 'another.student@hajat.com.ly',
        'password' => 'Test-pass-12345!',
        'password_confirmation' => 'Test-pass-12345!',
    ])->assertRedirect('/billing');
});

it('takes a signed-in customer straight to checkout', function () {
    actingAsMember(Role::Owner);

    $this->get('/start/pro')->assertRedirect('/billing?plan=pro');
});

it('refuses unknown plans', function () {
    $this->get('/start/nope')->assertNotFound();
});

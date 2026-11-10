<?php

namespace Falak\Billing;

use Falak\Billing\Application\Jobs\ReconcilePayments;
use Falak\Billing\Application\Jobs\SyncSpaces;
use Falak\Billing\Application\Operator;
use Falak\Billing\Application\Payments\PaymentGateways;
use Falak\Billing\Domain\Models\Subscription;
use Falak\Billing\Http\Middleware\KeepCustomersInTheirSpace;
use Falak\Billing\Infrastructure\Payments\AdfaliGateway;
use Falak\Billing\Infrastructure\Payments\DemoGateway;
use Falak\Billing\Infrastructure\Payments\ManualGateway;
use Falak\Billing\Infrastructure\Payments\MoamalatClient;
use Falak\Billing\Infrastructure\Payments\MoamalatGateway;
use Falak\Billing\Infrastructure\Payments\OnePayGateway;
use Falak\Billing\Infrastructure\Payments\PaymobGateway;
use Falak\Edge\Events\EdgeApplied;
use Falak\Identity\Contracts\CurrentOrganization;
use Falak\Identity\Contracts\PermissionRegistry;
use Falak\Identity\Contracts\Role;
use Falak\Kernel\Support\ModuleServiceProvider;
use Falak\Kernel\Support\SharedProps;
use Falak\Servers\Events\ServerProvisioned;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

class BillingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->modulePath().'/config/billing.php', 'billing');

        $this->app->singleton(PaymentGateways::class, fn (Application $app) => new PaymentGateways([
            $app->make(DemoGateway::class),
            $app->make(AdfaliGateway::class),
            $app->make(MoamalatGateway::class),
            ...OnePayGateway::all(),
            $app->make(PaymobGateway::class),
            $app->make(ManualGateway::class),
        ]));
    }

    protected function bootModule(): void
    {
        $this->app->make(Router::class)->pushMiddlewareToGroup('web', KeepCustomersInTheirSpace::class);

        RateLimiter::for('billing-payment-webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        if ($this->app->make(MoamalatGateway::class)->enabled()) {
            $origin = MoamalatClient::fromConfig()->lightboxOrigin();

            foreach (['script_src', 'frame_src', 'connect_src'] as $directive) {
                config(["kernel.csp.{$directive}" => [...(array) config("kernel.csp.{$directive}", []), $origin]]);
            }
        }

        $registry = $this->app->make(PermissionRegistry::class);
        $registry->register('billing.view', [Role::Admin, Role::Developer, Role::Viewer], 'See the plan, the space and payments', 'billing');
        $registry->register('billing.manage', [Role::Admin], 'Buy and renew plans', 'billing');
        $registry->register(Operator::PERMISSION, [Role::Admin], 'Operate hosting: plans, payment reviews and every space (operator organization only)', 'billing');

        Event::listen(EdgeApplied::class, fn () => SyncSpaces::dispatch());
        Event::listen(ServerProvisioned::class, fn () => SyncSpaces::dispatch());

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new SyncSpaces)->everyMinute()->name('billing:sync-spaces')->withoutOverlapping();
            $schedule->job(new ReconcilePayments)->everyFiveMinutes()->name('billing:reconcile-payments')->withoutOverlapping();
        });

        $this->app->make(SharedProps::class)->register('storefront', fn () => config('billing.landing') ? [
            'brand' => config('billing.brand'),
        ] : null, authenticated: false);

        $this->app->make(SharedProps::class)->register('billing', function (Request $request) {
            $organizationId = $this->app->make(CurrentOrganization::class)->id();

            if ($organizationId === null) {
                return null;
            }

            $operator = $this->app->make(Operator::class);
            $subscription = Subscription::currentFor($organizationId);

            return [
                'operator' => $operator->is($organizationId),
                'customer' => KeepCustomersInTheirSpace::isCustomerOrganization($organizationId, $operator),
                'brand' => config('billing.brand'),
                'can_operate' => $operator->can($request->user(), $organizationId),
                'active' => (bool) $subscription?->isActive(),
                'plan' => $subscription?->plan?->name,
                'ends_at' => $subscription?->ends_at?->toIso8601String(),
            ];
        }, authenticated: true);
    }
}

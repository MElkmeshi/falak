<?php

namespace Falak\Billing\Http\Controllers;

use Falak\Billing\Application\OneClickCatalog;
use Falak\Billing\Application\Payments\PaymentGateways;
use Falak\Billing\Domain\Models\Plan;
use Falak\Kernel\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LandingController extends Controller
{
    public function index(OneClickCatalog $catalog, PaymentGateways $gateways): Response
    {
        $apps = $catalog->apps();

        return Inertia::render('Billing/Landing', [
            'brand' => config('billing.brand'),
            'plans' => $catalog->cards(Plan::query()->available()->get(), $apps),
            'apps' => $apps,
            'currencies' => config('billing.currencies'),
            'payment_methods' => array_values(array_map(
                fn (array $gateway) => $gateway['label'],
                array_filter($gateways->options(), fn (array $gateway) => $gateway['key'] !== 'demo'),
            )),
            'app_domain' => config('sites.test_domain'),
        ]);
    }

    public function start(Request $request, ?string $plan = null): RedirectResponse
    {
        $checkout = $plan === null ? '/billing' : '/billing?plan='.Plan::query()->available()->where('slug', $plan)->firstOrFail()->slug;

        if ($request->user() !== null) {
            return redirect($checkout);
        }

        $request->session()->put('url.intended', $checkout);

        return redirect()->route('register');
    }
}

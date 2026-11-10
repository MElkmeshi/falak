<?php

namespace Falak\Billing\Http\Controllers;

use Falak\Billing\Application\Actions\ConfirmPayment;
use Falak\Billing\Application\Actions\StartCheckout;
use Falak\Billing\Domain\Models\Payment;
use Falak\Billing\Domain\Models\Plan;
use Falak\Identity\Contracts\CurrentOrganization;
use Falak\Identity\Contracts\OrganizationAccess;
use Falak\Kernel\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CheckoutController extends Controller
{
    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly OrganizationAccess $access,
    ) {}

    public function start(Request $request, StartCheckout $checkout): RedirectResponse
    {
        $organizationId = $this->organization->requireId();
        $this->access->authorize($request->user(), $organizationId, 'billing.manage');

        $data = $request->validate([
            'plan_id' => ['required', 'string', 'size:26'],
            'provider' => ['required', 'string', 'max:32'],
            'currency' => ['required', 'string', 'size:3'],
            'fields' => ['nullable', 'array', 'max:10'],
            'fields.*' => ['nullable', 'string', 'max:190'],
        ]);

        $plan = Plan::query()->findOrFail(strtolower($data['plan_id']));
        $payment = $checkout($organizationId, $request->user()?->getAuthIdentifier(), $plan, $data['provider'], $data['currency'], (array) ($data['fields'] ?? []));

        return to_route('billing.payments.show', $payment->id);
    }

    public function show(Request $request, string $payment): Response
    {
        $row = $this->payment($request, $payment, 'billing.view');

        return Inertia::render('Billing/Payment', [
            'payment' => $row->toRow(),
            'step' => $row->meta['step'] ?? null,
        ]);
    }

    public function confirm(Request $request, string $payment, ConfirmPayment $confirm): RedirectResponse
    {
        $row = $this->payment($request, $payment, 'billing.manage');
        $data = $request->validate(['otp' => ['required', 'string', 'max:12']]);
        $confirm($row, $data);

        return to_route('billing.payments.show', $row->id);
    }

    private function payment(Request $request, string $id, string $permission): Payment
    {
        $organizationId = $this->organization->requireId();
        $this->access->authorize($request->user(), $organizationId, $permission);

        return Payment::query()->with('plan')->where('organization_id', $organizationId)->findOrFail(strtolower($id));
    }
}

<?php

namespace Falak\Billing\Http\Controllers;

use Falak\Billing\Application\Actions\ReconcilePayment;
use Falak\Billing\Domain\Enums\PaymentStatus;
use Falak\Billing\Domain\Models\Payment;
use Falak\Billing\Infrastructure\Payments\MoamalatClient;
use Falak\Identity\Contracts\CurrentOrganization;
use Falak\Identity\Contracts\OrganizationAccess;
use Falak\Kernel\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class MoamalatController extends Controller
{
    public function checkout(Request $request, string $payment, CurrentOrganization $organization, OrganizationAccess $access): JsonResponse
    {
        $row = $this->awaiting($request, $payment, $organization, $access);
        $client = MoamalatClient::fromConfig();

        return response()->json(['script' => $client->lightboxScript(), 'config' => $client->checkout($row->id, (string) $row->amount)])
            ->header('Cache-Control', 'no-store');
    }

    public function complete(Request $request, string $payment, CurrentOrganization $organization, OrganizationAccess $access, ReconcilePayment $reconcile): RedirectResponse
    {
        $row = $this->awaiting($request, $payment, $organization, $access);
        $data = $request->validate(['network_reference' => ['nullable', 'string', 'max:64']]);

        if (! $reconcile($row, $data['network_reference'] ?? null)) {
            return to_route('billing.payments.show', $row->id)->with('flash', ['type' => 'info', 'message' => 'We are waiting for the bank to confirm the payment. This page updates by itself.']);
        }

        return to_route('billing.payments.show', $row->id);
    }

    public function notify(Request $request, ReconcilePayment $reconcile): JsonResponse
    {
        $notification = $request->all();

        if (! MoamalatClient::fromConfig()->notificationIsAuthentic($notification)) {
            Log::warning('billing: rejected a Moamalat notification with a bad signature', ['ip' => $request->ip()]);

            return response()->json(['Message' => 'Invalid signature', 'Success' => false], 401);
        }

        $payment = Payment::query()
            ->where('provider', 'moamalat')
            ->whereKey(strtolower((string) ($notification['MerchantReference'] ?? '')))
            ->first();

        if ($payment !== null && ($notification['ActionCode'] ?? null) === '00' && (string) ($notification['TxnType'] ?? '1') === '1') {
            $reconcile($payment, is_scalar($notification['NetworkReference'] ?? null) ? (string) $notification['NetworkReference'] : null);
        }

        return response()->json(['Message' => 'Success', 'Success' => true]);
    }

    private function awaiting(Request $request, string $id, CurrentOrganization $organization, OrganizationAccess $access): Payment
    {
        $organizationId = $organization->requireId();
        $access->authorize($request->user(), $organizationId, 'billing.manage');

        $payment = Payment::query()->where('organization_id', $organizationId)->where('provider', 'moamalat')->findOrFail(strtolower($id));
        abort_unless(in_array($payment->status, [PaymentStatus::AwaitingGateway, PaymentStatus::Paid], true), 409, 'This payment is no longer open.');

        return $payment;
    }
}

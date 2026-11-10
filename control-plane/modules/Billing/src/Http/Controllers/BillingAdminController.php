<?php

namespace Falak\Billing\Http\Controllers;

use Falak\Billing\Application\Actions\ActivatePayment;
use Falak\Billing\Application\Jobs\ProvisionSpace;
use Falak\Billing\Application\Jobs\SyncSpaces;
use Falak\Billing\Application\Operator;
use Falak\Billing\Domain\Enums\PaymentStatus;
use Falak\Billing\Domain\Enums\SpaceStatus;
use Falak\Billing\Domain\Models\Payment;
use Falak\Billing\Domain\Models\Plan;
use Falak\Billing\Domain\Models\Space;
use Falak\Billing\Domain\Models\Subscription;
use Falak\Billing\Infrastructure\IncusClient;
use Falak\Identity\Contracts\CurrentOrganization;
use Falak\Identity\Contracts\OrganizationDirectory;
use Falak\Kernel\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class BillingAdminController extends Controller
{
    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly Operator $operator,
    ) {}

    public function index(Request $request, OrganizationDirectory $organizations): Response
    {
        $this->authorizeOperator($request);

        $names = [];
        foreach ($organizations->all() as $org) {
            $names[$org->id] = $org->name;
        }

        $subscriptions = Subscription::query()->with('plan')->get()->keyBy('organization_id');

        return Inertia::render('Billing/admin/Index', [
            'plans' => Plan::query()->orderBy('sort')->get()->map->toCard()->values(),
            'payments' => Payment::query()->with('plan')->latest()->limit(100)->get()->map(fn (Payment $p) => $p->toRow() + ['organization' => $names[$p->organization_id] ?? $p->organization_id])->values(),
            'spaces' => Space::query()->latest()->get()->map(fn (Space $s) => $s->toCard() + [
                'organization' => $names[$s->organization_id] ?? $s->organization_id,
                'plan' => $subscriptions[$s->organization_id]?->plan?->name,
                'ends_at' => $subscriptions[$s->organization_id]?->ends_at?->toIso8601String(),
            ])->values(),
            'pending_reviews' => Payment::query()->where('status', PaymentStatus::AwaitingReview)->count(),
        ]);
    }

    public function approve(Request $request, string $payment, ActivatePayment $activate): RedirectResponse
    {
        $this->authorizeOperator($request);
        $row = Payment::query()->findOrFail(strtolower($payment));
        abort_unless($row->status === PaymentStatus::AwaitingReview, 422, 'Only payments waiting for review can be approved.');
        $activate($row, (string) $request->user()?->getAuthIdentifier());

        return back()->with('success', 'Payment approved; the space is being prepared.');
    }

    public function reject(Request $request, string $payment): RedirectResponse
    {
        $this->authorizeOperator($request);
        $row = Payment::query()->findOrFail(strtolower($payment));
        abort_unless($row->status === PaymentStatus::AwaitingReview, 422, 'Only payments waiting for review can be rejected.');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $row->forceFill(['status' => PaymentStatus::Rejected, 'failure' => $data['reason'] ?? 'Rejected by an operator.', 'reviewed_by' => $request->user()?->getAuthIdentifier()])->save();

        return back()->with('success', 'Payment rejected.');
    }

    public function space(Request $request, string $space, string $action, IncusClient $incus): RedirectResponse
    {
        $this->authorizeOperator($request);
        $row = Space::query()->findOrFail(strtolower($space));

        match ($action) {
            'suspend' => [$incus->stop($row->instance), $row->forceFill(['status' => SpaceStatus::Suspended])->save()],
            'resume' => [$incus->start($row->instance), $row->forceFill(['status' => SpaceStatus::Ready])->save()],
            'reprovision' => ProvisionSpace::dispatch($row->organization_id, $request->user()?->getAuthIdentifier()),
        };

        SyncSpaces::dispatch();

        return back()->with('success', "Space {$action} requested.");
    }

    public function updatePlan(Request $request, string $plan): RedirectResponse
    {
        $this->authorizeOperator($request);
        Plan::query()->findOrFail(strtolower($plan))->fill($this->planData($request))->save();

        return back()->with('success', 'Plan saved. Existing spaces pick up new limits at their next payment or reprovision.');
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $this->authorizeOperator($request);
        $data = $this->planData($request) + $request->validate(['slug' => ['required', 'alpha_dash', 'max:64', Rule::unique('billing_plans', 'slug')]]);
        Plan::query()->create($data + ['sort' => (int) Plan::query()->max('sort') + 10]);

        return back()->with('success', 'Plan created.');
    }

    private function planData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_lyd' => ['required', 'numeric', 'min:0', 'max:100000'],
            'price_egp' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'period_days' => ['required', 'integer', 'min:1', 'max:366'],
            'cpus' => ['required', 'numeric', 'min:0.1', 'max:16'],
            'memory_mb' => ['required', 'integer', 'min:256', 'max:65536'],
            'disk_gb' => ['required', 'integer', 'min:2', 'max:500'],
            'max_apps' => ['required', 'integer', 'min:1', 'max:500'],
            'max_databases' => ['required', 'integer', 'min:0', 'max:100'],
            'featured' => ['boolean'],
            'active' => ['boolean'],
        ]);
    }

    private function authorizeOperator(Request $request): void
    {
        $organizationId = $this->organization->requireId();
        abort_unless($this->operator->is($organizationId), 404);
        abort_unless($this->operator->can($request->user(), $organizationId), 403);
    }
}

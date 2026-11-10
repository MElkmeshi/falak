<?php

namespace Falak\Billing\Http\Controllers;

use Falak\Billing\Application\Payments\PaymentGateways;
use Falak\Billing\Application\SpaceOverview;
use Falak\Deployments\Contracts\DeploymentTrigger;
use Falak\Identity\Contracts\CurrentOrganization;
use Falak\Identity\Contracts\OrganizationAccess;
use Falak\Kernel\Http\Controller;
use Falak\Sites\Contracts\Data\SiteData;
use Falak\Sites\Contracts\SiteDirectory;
use Falak\Sites\Contracts\SiteFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController extends Controller
{
    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly OrganizationAccess $access,
        private readonly SpaceOverview $overview,
    ) {}

    public function home(Request $request): Response
    {
        $organizationId = $this->viewable($request);

        return Inertia::render('Billing/Customer/Home', $this->overview->plan($organizationId) + [
            'apps' => $this->overview->apps($organizationId),
            'store' => $this->overview->store($organizationId),
            'plans' => $this->overview->plans($organizationId),
        ]);
    }

    public function apps(Request $request): Response
    {
        $organizationId = $this->viewable($request);

        return Inertia::render('Billing/Customer/Apps', $this->overview->plan($organizationId) + [
            'apps' => $this->overview->apps($organizationId),
        ]);
    }

    public function store(Request $request): Response
    {
        $organizationId = $this->viewable($request);

        return Inertia::render('Billing/Customer/Store', $this->overview->plan($organizationId) + [
            'store' => $this->overview->store($organizationId),
            'app_count' => count($this->overview->apps($organizationId)),
        ]);
    }

    public function plan(Request $request, PaymentGateways $gateways): Response
    {
        $organizationId = $this->viewable($request);

        return Inertia::render('Billing/Customer/Plan', $this->overview->plan($organizationId) + [
            'plans' => $this->overview->plans($organizationId),
            'payments' => $this->overview->payments($organizationId),
            'gateways' => $gateways->options(),
            'currencies' => config('billing.currencies'),
            'selected_plan' => $request->query('plan'),
            'can_manage' => $this->access->can($request->user(), $organizationId, 'billing.manage'),
        ]);
    }

    public function account(Request $request): Response
    {
        $this->viewable($request);

        return Inertia::render('Billing/Customer/Account', [
            'must_verify' => $request->user() instanceof MustVerifyEmail && ! $request->user()->hasVerifiedEmail(),
        ]);
    }

    public function restart(Request $request, string $site, SiteDirectory $sites, DeploymentTrigger $trigger): RedirectResponse
    {
        $found = $this->ownedSite($request, $site, 'sites.manage', $sites);
        $trigger->deploy($found->id, $request->user()?->getAuthIdentifier(), message: 'Restarted from My apps');

        return back()->with('success', "Restarting {$found->name}. It will be back in a minute.");
    }

    public function destroy(Request $request, string $site, SiteDirectory $sites, SiteFactory $factory): RedirectResponse
    {
        $found = $this->ownedSite($request, $site, 'sites.delete', $sites);
        $factory->delete($found->id, [], $request->user()?->getAuthIdentifier());

        return redirect('/apps')->with('success', "{$found->name} was deleted.");
    }

    private function viewable(Request $request): string
    {
        $organizationId = $this->organization->requireId();
        $this->access->authorize($request->user(), $organizationId, 'billing.view');

        return $organizationId;
    }

    private function ownedSite(Request $request, string $siteId, string $permission, SiteDirectory $sites): SiteData
    {
        $organizationId = $this->organization->requireId();
        $this->access->authorize($request->user(), $organizationId, $permission);
        $site = $sites->find($siteId);
        abort_if($site === null || $site->organizationId !== $organizationId, 404);

        return $site;
    }
}

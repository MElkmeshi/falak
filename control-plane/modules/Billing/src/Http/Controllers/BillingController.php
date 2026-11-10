<?php

namespace Falak\Billing\Http\Controllers;

use Falak\Billing\Application\Actions\DeployOneClickApp;
use Falak\Identity\Contracts\CurrentOrganization;
use Falak\Identity\Contracts\OrganizationAccess;
use Falak\Kernel\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class BillingController extends Controller
{
    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly OrganizationAccess $access,
    ) {}

    public function deployApp(Request $request, string $slug, DeployOneClickApp $deploy): RedirectResponse
    {
        $organizationId = $this->organization->requireId();
        $this->access->authorize($request->user(), $organizationId, 'sites.create');

        $result = $deploy($organizationId, $request->user()?->getAuthIdentifier(), $slug);
        $url = $result['domains'] !== [] ? ' at https://'.array_values($result['domains'])[0] : '';

        return redirect('/apps')->with('success', "{$result['name']} is starting{$url}. This takes about a minute.");
    }
}

<?php

namespace Falak\Billing\Application;

use Falak\Billing\Domain\Models\Payment;
use Falak\Billing\Domain\Models\Plan;
use Falak\Billing\Domain\Models\Space;
use Falak\Billing\Domain\Models\Subscription;
use Falak\Billing\Infrastructure\IncusClient;
use Falak\Deployments\Contracts\Data\DeploymentSummary;
use Falak\Deployments\Contracts\DeploymentDirectory;
use Falak\Sites\Contracts\Data\SiteData;
use Falak\Sites\Contracts\SiteDirectory;
use Throwable;

final class SpaceOverview
{
    public function __construct(
        private readonly IncusClient $incus,
        private readonly SiteDirectory $sites,
        private readonly DeploymentDirectory $deployments,
        private readonly OneClickCatalog $catalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function plan(string $organizationId): array
    {
        $subscription = Subscription::currentFor($organizationId);
        $space = Space::query()->where('organization_id', $organizationId)->first();

        return [
            'subscription' => $subscription ? [
                'plan' => $subscription->plan->toCard() + ['runs' => []],
                'status' => $subscription->isActive() ? 'active' : 'expired',
                'ends_at' => $subscription->ends_at?->toIso8601String(),
            ] : null,
            'space' => $space?->toCard(),
            'usage' => $this->usage($space),
        ];
    }

    /**
     * @return list<array{id: string, name: string, icon: ?string, url: ?string, status: string, error: ?string, updated_at: ?string}>
     */
    public function apps(string $organizationId): array
    {
        $sites = $this->sites->forOrganization($organizationId);
        $current = $this->deployments->currentForSites(array_map(fn (SiteData $site) => $site->id, $sites));

        return array_map(fn (SiteData $site) => [
            'id' => $site->id,
            'name' => $site->name,
            'icon' => $site->compose?->template['slug'] ?? null,
            'url' => $this->urlOf($site),
            'status' => $this->statusOf($current[$site->id] ?? null),
            'error' => ($current[$site->id] ?? null)?->status === 'failed' ? 'The last start did not finish. Restart it to try again.' : null,
            'updated_at' => ($current[$site->id] ?? null)?->createdAt->format(DATE_ATOM),
        ], $sites);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function store(string $organizationId): array
    {
        $subscription = Subscription::currentFor($organizationId);
        $plans = Plan::query()->available()->get();

        return array_map(fn (array $app) => $app + [
            'fits' => $subscription === null || $subscription->plan->runs($app['min_memory_mb']),
            'needs_plan' => $plans->first(fn (Plan $plan) => $plan->runs($app['min_memory_mb']))?->name,
        ], $this->catalog->apps($organizationId));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function plans(string $organizationId): array
    {
        return $this->catalog->cards(Plan::query()->available()->get(), $this->catalog->apps($organizationId));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payments(string $organizationId): array
    {
        return Payment::query()->with('plan')->where('organization_id', $organizationId)->latest()->limit(20)->get()->map->toRow()->values()->all();
    }

    /**
     * @return array{memory_mb: ?int, disk_gb: ?float, running: bool}|null
     */
    private function usage(?Space $space): ?array
    {
        if ($space === null) {
            return null;
        }

        try {
            $state = $this->incus->state($space->instance);
        } catch (Throwable) {
            return null;
        }

        return [
            'memory_mb' => (int) round($state['memory_bytes'] / 1048576),
            'disk_gb' => round($state['disk_bytes'] / 1073741824, 1),
            'running' => $state['status'] === 'Running',
        ];
    }

    private function urlOf(SiteData $site): ?string
    {
        foreach ($site->compose?->publicServices ?? [] as $service) {
            if (($url = $service->url()) !== null) {
                return $url;
            }
        }

        return $site->testDomain !== null ? 'https://'.$site->testDomain : null;
    }

    private function statusOf(?DeploymentSummary $deployment): string
    {
        return match ($deployment?->status) {
            null => 'stopped',
            'succeeded' => 'running',
            'failed', 'cancelled' => 'failed',
            default => 'starting',
        };
    }
}

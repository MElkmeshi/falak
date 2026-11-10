<?php

namespace Falak\Billing\Application\Actions;

use Falak\Billing\Domain\Enums\SpaceStatus;
use Falak\Billing\Domain\Models\Space;
use Falak\Billing\Domain\Models\Subscription;
use Falak\Sites\Contracts\SiteDirectory;
use Falak\Templates\Contracts\TemplateDeployer;
use Illuminate\Validation\ValidationException;

final class DeployOneClickApp
{
    public function __construct(
        private readonly TemplateDeployer $templates,
        private readonly SiteDirectory $sites,
    ) {}

    public function __invoke(string $organizationId, ?string $userId, string $slug): array
    {
        if (! in_array($slug, (array) config('billing.one_click'), true)) {
            throw ValidationException::withMessages(['app' => 'This app is not offered.']);
        }

        $subscription = Subscription::currentFor($organizationId);
        $space = Space::query()->where('organization_id', $organizationId)->first();

        if (! $subscription?->isActive() || $space?->status !== SpaceStatus::Ready || $space->server_id === null) {
            throw ValidationException::withMessages(['app' => 'Your space is not ready yet.']);
        }

        if (count($this->sites->forOrganization($organizationId)) >= $subscription->plan->max_apps) {
            throw ValidationException::withMessages(['app' => "Your {$subscription->plan->name} plan includes {$subscription->plan->max_apps} apps. Upgrade to add more."]);
        }

        $template = $this->templates->catalog($organizationId, [$slug])[0] ?? null;

        if ($template === null) {
            throw ValidationException::withMessages(['app' => 'This app is not in the catalog.']);
        }

        if (! $subscription->plan->runs($template['min_memory_mb'])) {
            throw ValidationException::withMessages(['app' => "{$template['name']} needs more memory than the {$subscription->plan->name} plan has. Upgrade to deploy it."]);
        }

        return $this->templates->deploy($organizationId, $userId, $slug, [$space->server_id]);
    }
}

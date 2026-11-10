<?php

namespace Falak\Billing\Application;

use Falak\Billing\Domain\Models\Plan;
use Falak\Templates\Contracts\TemplateDeployer;
use Illuminate\Support\Collection;

final class OneClickCatalog
{
    public function __construct(
        private readonly TemplateDeployer $templates,
        private readonly Operator $operator,
    ) {}

    /**
     * @return list<array{slug: string, name: string, description: string, icon: ?string, min_memory_mb: ?int}>
     */
    public function apps(?string $organizationId = null): array
    {
        $organizationId ??= $this->operator->organizationId();

        return $organizationId === null ? [] : $this->templates->catalog($organizationId, (array) config('billing.one_click'));
    }

    /**
     * @param  Collection<int, Plan>  $plans
     * @param  list<array{name: string, min_memory_mb: ?int}>  $apps
     * @return list<array<string, mixed>>
     */
    public function cards(Collection $plans, array $apps): array
    {
        return $plans->map(fn (Plan $plan) => $plan->toCard() + [
            'runs' => array_values(array_map(fn (array $app) => $app['name'], array_filter($apps, fn (array $app) => $plan->runs($app['min_memory_mb'])))),
        ])->values()->all();
    }
}

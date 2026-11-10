<?php

namespace Falak\Billing\Tests\Support;

use Falak\Templates\Contracts\TemplateDeployer;

final class FakeTemplates implements TemplateDeployer
{
    public array $deployed = [];

    public function __construct(private readonly array $minMemory = ['n8n' => 512, 'uptime-kuma' => 256]) {}

    public static function install(array $minMemory = ['n8n' => 512, 'uptime-kuma' => 256]): self
    {
        $fake = new self($minMemory);
        app()->instance(TemplateDeployer::class, $fake);
        config(['billing.one_click' => array_keys($minMemory)]);

        return $fake;
    }

    public function catalog(string $organizationId, array $slugs): array
    {
        return array_values(array_map(fn (string $slug) => [
            'slug' => $slug,
            'name' => $slug === 'uptime-kuma' ? 'Uptime Kuma' : $slug,
            'description' => '',
            'icon' => null,
            'min_memory_mb' => $this->minMemory[$slug],
        ], array_filter($slugs, fn (string $slug) => isset($this->minMemory[$slug]))));
    }

    public function deploy(string $organizationId, ?string $userId, string $slug, array $serverIds, array $inputs = [], ?string $name = null): array
    {
        $this->deployed[] = $slug;

        return ['site_id' => 'site', 'name' => $slug, 'domains' => [], 'deployment_id' => null, 'warnings' => []];
    }
}

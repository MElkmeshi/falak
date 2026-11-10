<?php

namespace Falak\Templates\Contracts;

/**
 * One-click template deploys for other modules: the catalog entries by slug, and a deploy into the organization's
 * default environment on the given servers, with every input left to its generator or default.
 */
interface TemplateDeployer
{
    /**
     * @param  list<string>  $slugs
     * @return list<array{slug: string, name: string, description: string, icon: ?string, min_memory_mb: ?int}>
     */
    public function catalog(string $organizationId, array $slugs): array;

    /**
     * @param  list<string>  $serverIds
     * @param  array<string, mixed>  $inputs
     * @return array{site_id: string, name: string, domains: array<string, string>, deployment_id: ?string, warnings: list<string>}
     */
    public function deploy(string $organizationId, ?string $userId, string $slug, array $serverIds, array $inputs = [], ?string $name = null): array;
}

<?php

namespace Falak\Templates\Infrastructure;

use Falak\Identity\Contracts\CurrentOrganization;
use Falak\Projects\Contracts\ProjectDirectory;
use Falak\Templates\Application\Actions\DeployTemplate;
use Falak\Templates\Application\Catalog\TemplateRepository;
use Falak\Templates\Contracts\TemplateDeployer;
use Illuminate\Validation\ValidationException;

final class CatalogTemplateDeployer implements TemplateDeployer
{
    public function __construct(
        private readonly TemplateRepository $templates,
        private readonly ProjectDirectory $projects,
        private readonly CurrentOrganization $organization,
        private readonly DeployTemplate $deployTemplate,
    ) {}

    public function catalog(string $organizationId, array $slugs): array
    {
        $found = [];

        foreach ($slugs as $slug) {
            $template = $this->templates->find($organizationId, $slug);

            if ($template === null) {
                continue;
            }

            $found[] = [
                'slug' => $template->slug,
                'name' => $template->name,
                'description' => $template->description,
                'icon' => $template->icon,
                'min_memory_mb' => $template->minMemoryMb,
            ];
        }

        return $found;
    }

    public function deploy(string $organizationId, ?string $userId, string $slug, array $serverIds, array $inputs = [], ?string $name = null): array
    {
        return $this->organization->run($organizationId, function () use ($organizationId, $userId, $slug, $serverIds, $inputs, $name) {
            $template = $this->templates->find($organizationId, $slug)
                ?? throw ValidationException::withMessages(['template' => "Template {$slug} is not in the catalog."]);
            $environment = $this->projects->defaultEnvironment($organizationId)
                ?? throw ValidationException::withMessages(['template' => 'The organization has no default environment.']);

            $result = ($this->deployTemplate)($environment, $userId, $template, $inputs, [], array_values($serverIds), $name);

            return [
                'site_id' => $result->site->id,
                'name' => $result->site->name,
                'domains' => $result->domains,
                'deployment_id' => $result->deploymentId,
                'warnings' => $result->warnings,
            ];
        });
    }
}

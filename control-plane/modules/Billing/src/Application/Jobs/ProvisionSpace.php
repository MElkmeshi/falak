<?php

namespace Falak\Billing\Application\Jobs;

use Falak\Billing\Domain\Enums\SpaceStatus;
use Falak\Billing\Domain\Models\Space;
use Falak\Billing\Domain\Models\Subscription;
use Falak\Billing\Infrastructure\IncusClient;
use Falak\Servers\Contracts\CustomServers;
use Falak\Servers\Contracts\ServerDirectory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

final class ProvisionSpace implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public string $organizationId, public ?string $userId = null) {}

    public function uniqueId(): string
    {
        return $this->organizationId;
    }

    public function handle(IncusClient $incus, CustomServers $servers, ServerDirectory $directory): void
    {
        $subscription = Subscription::currentFor($this->organizationId);

        if (! $subscription?->isActive()) {
            return;
        }

        $plan = $subscription->plan;
        $space = Space::query()->firstOrCreate(['organization_id' => $this->organizationId], [
            'instance' => 'space-'.Str::lower(substr($this->organizationId, -10)),
            'status' => SpaceStatus::Provisioning,
            'cpus' => $plan->cpus,
            'memory_mb' => $plan->memory_mb,
            'disk_gb' => $plan->disk_gb,
        ]);

        try {
            if ($space->server_id === null || $directory->find($space->server_id) === null) {
                $space->forceFill(['status' => SpaceStatus::Provisioning, 'error' => null])->save();
                $incus->delete($space->instance);
                $server = $servers->create($this->organizationId, $this->userId, 'My space');
                $space->forceFill(['server_id' => $server['server_id']])->save();
                $incus->create($space->instance, $plan->cpus, $plan->memory_mb, $plan->disk_gb, $server['install_url']);
                $incus->start($space->instance);
                $space->forceFill(['status' => SpaceStatus::Enrolling])->save();
            } else {
                $incus->resize($space->instance, $plan->cpus, $plan->memory_mb, $plan->disk_gb);

                if ($incus->state($space->instance)['status'] !== 'Running') {
                    $incus->start($space->instance);
                }

                $space->forceFill(['status' => $space->status === SpaceStatus::Suspended ? SpaceStatus::Ready : $space->status, 'error' => null])->save();
            }

            $space->forceFill(['cpus' => $plan->cpus, 'memory_mb' => $plan->memory_mb, 'disk_gb' => $plan->disk_gb])->save();
        } catch (Throwable $e) {
            $space->forceFill(['status' => SpaceStatus::Error, 'error' => Str::limit($e->getMessage(), 990)])->save();

            throw $e;
        }

        SyncSpaces::dispatch();
    }
}

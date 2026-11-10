<?php

namespace Falak\Billing\Application\Jobs;

use Falak\Billing\Domain\Enums\SpaceStatus;
use Falak\Billing\Domain\Enums\SubscriptionStatus;
use Falak\Billing\Domain\Models\Space;
use Falak\Billing\Domain\Models\Subscription;
use Falak\Billing\Infrastructure\IncusClient;
use Falak\Billing\Infrastructure\SpaceRouter;
use Falak\Servers\Contracts\ServerDirectory;
use Falak\Servers\Contracts\ServerStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncSpaces implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $uniqueFor = 120;

    public function handle(IncusClient $incus, ServerDirectory $servers, SpaceRouter $router): void
    {
        $this->expire($incus);

        foreach (Space::query()->whereIn('status', [SpaceStatus::Enrolling, SpaceStatus::Ready, SpaceStatus::Error])->get() as $space) {
            try {
                $state = $incus->state($space->instance);
            } catch (Throwable $e) {
                Log::warning('billing: incus state failed', ['space' => $space->id, 'error' => $e->getMessage()]);

                continue;
            }

            $server = $space->server_id ? $servers->find($space->server_id) : null;
            $status = match (true) {
                $server === null => $space->status,
                $server->status === ServerStatus::Active => SpaceStatus::Ready,
                in_array($server->status, [ServerStatus::Error, ServerStatus::NeedsAttention], true) => SpaceStatus::Error,
                default => SpaceStatus::Enrolling,
            };

            $space->forceFill([
                'ipv4' => $state['ipv4'] ?? $space->ipv4,
                'status' => $status,
                'error' => $status === SpaceStatus::Error ? 'Falak could not finish setting up the space; an operator was notified.' : null,
            ])->save();
        }

        $router->push();
    }

    private function expire(IncusClient $incus): void
    {
        $expired = Subscription::query()->where('status', SubscriptionStatus::Active)->where('ends_at', '<', now())->get();

        foreach ($expired as $subscription) {
            $subscription->forceFill(['status' => SubscriptionStatus::Expired])->save();
            $space = Space::query()->where('organization_id', $subscription->organization_id)->first();

            if ($space === null || $space->status === SpaceStatus::Suspended) {
                continue;
            }

            try {
                $incus->stop($space->instance);
                $space->forceFill(['status' => SpaceStatus::Suspended])->save();
            } catch (Throwable $e) {
                Log::warning('billing: suspending an expired space failed', ['space' => $space->id, 'error' => $e->getMessage()]);
            }
        }
    }
}

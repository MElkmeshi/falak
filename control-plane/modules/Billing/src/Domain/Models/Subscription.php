<?php

namespace Falak\Billing\Domain\Models;

use Falak\Billing\Domain\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasUlids;

    protected $table = 'billing_subscriptions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public static function currentFor(string $organizationId): ?self
    {
        return self::query()->with('plan')->where('organization_id', $organizationId)->latest('ends_at')->first();
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active && $this->ends_at?->isFuture();
    }
}

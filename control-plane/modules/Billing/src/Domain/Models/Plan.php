<?php

namespace Falak\Billing\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasUlids;

    protected $table = 'billing_plans';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_lyd' => 'decimal:2',
            'price_egp' => 'decimal:2',
            'cpus' => 'float',
            'memory_mb' => 'integer',
            'disk_gb' => 'integer',
            'max_apps' => 'integer',
            'max_databases' => 'integer',
            'period_days' => 'integer',
            'featured' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function runs(?int $appMemoryMb): bool
    {
        return $this->memory_mb >= ($appMemoryMb ?? 0) + (int) config('billing.space_overhead_mb');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('sort');
    }

    public function price(string $currency): string
    {
        return $currency === 'EGP' ? (string) $this->price_egp : (string) $this->price_lyd;
    }

    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'price_lyd' => (string) $this->price_lyd,
            'price_egp' => (string) $this->price_egp,
            'period_days' => $this->period_days,
            'cpus' => $this->cpus,
            'memory_mb' => $this->memory_mb,
            'disk_gb' => $this->disk_gb,
            'max_apps' => $this->max_apps,
            'max_databases' => $this->max_databases,
            'featured' => $this->featured,
            'active' => $this->active,
        ];
    }
}

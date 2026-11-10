<?php

namespace Falak\Billing\Domain\Models;

use Falak\Billing\Domain\Enums\SpaceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Space extends Model
{
    use HasUlids;

    protected $table = 'billing_spaces';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SpaceStatus::class,
            'cpus' => 'float',
            'memory_mb' => 'integer',
            'disk_gb' => 'integer',
        ];
    }

    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'server_id' => $this->server_id,
            'instance' => $this->instance,
            'ipv4' => $this->ipv4,
            'cpus' => $this->cpus,
            'memory_mb' => $this->memory_mb,
            'disk_gb' => $this->disk_gb,
            'error' => $this->error,
        ];
    }
}

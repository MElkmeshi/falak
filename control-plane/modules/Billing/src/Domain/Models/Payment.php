<?php

namespace Falak\Billing\Domain\Models;

use Falak\Billing\Domain\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUlids;

    protected $table = 'billing_payments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'meta' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'plan' => $this->plan?->name,
            'provider' => $this->provider,
            'amount' => (string) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'payer' => $this->payer,
            'reference' => $this->reference,
            'failure' => $this->failure,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

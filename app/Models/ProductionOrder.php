<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProductionOrder extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'production_orders';

    const STATUSES = ['queued', 'in_progress', 'completed', 'cancelled'];

    const TRANSITIONS = [
        'queued'      => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed'   => [],
        'cancelled'   => [],
    ];

    protected $fillable = [
        'order_item_id',
        'assigned_worker_id',
        'status',
        'started_at',
        'completed_at',
        'internal_notes',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_worker_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeByStatus(Builder $query, string|array $status): Builder
    {
        return $query->whereIn('status', (array) $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeForOrder(Builder $query, string $orderId): Builder
    {
        return $query->whereHas(
            'orderItem',
            fn ($q) => $q->where('order_id', $orderId)
        );
    }

    public function scopeForWorker(Builder $query, string $workerId): Builder
    {
        return $query->where('assigned_worker_id', $workerId);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_worker_id');
    }

    // -------------------------------------------------------------------------
    // Computed attributes
    // -------------------------------------------------------------------------

    public function getAllowedTransitionsAttribute(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    public function getIsActiveAttribute(): bool
    {
        return !in_array($this->status, ['completed', 'cancelled']);
    }

    public function getCanStartAttribute(): bool
    {
        return in_array('in_progress', $this->allowed_transitions);
    }

    public function getCanCompleteAttribute(): bool
    {
        return in_array('completed', $this->allowed_transitions);
    }

    public function getCanCancelAttribute(): bool
    {
        return in_array('cancelled', $this->allowed_transitions);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowed_transitions);
    }
}

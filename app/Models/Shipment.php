<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Shipment extends Model
{
    use HasFactory, HasUuids;

    const STATUSES = ['preparing', 'shipped', 'in_transit', 'delivered', 'failed'];

    /**
     * Transiciones permitidas por estado.
     * 'failed' es terminal: se crea un nuevo envío para reenvíos.
     */
    const TRANSITIONS = [
        'preparing'  => ['shipped', 'failed'],
        'shipped'    => ['in_transit', 'delivered', 'failed'],
        'in_transit' => ['delivered', 'failed'],
        'delivered'  => [],
        'failed'     => [],
    ];

    protected $fillable = [
        'order_id',
        'tracking_number',
        'courier_name',
        'handler_id',
        'status',
        'failure_reason',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'shipped_at'   => 'datetime',
        'delivered_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForOrder(Builder $query, string $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['delivered', 'failed']);
    }

    public function scopeByStatus(Builder $query, string|array $status): Builder
    {
        return $query->whereIn('status', (array) $status);
    }

    public function scopeWithCourier(Builder $query, string $courier): Builder
    {
        return $query->where('courier_name', 'like', "%{$courier}%");
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
        return !in_array($this->status, ['delivered', 'failed']);
    }

    public function getIsDeliveredAttribute(): bool
    {
        return $this->status === 'delivered';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowed_transitions);
    }
}

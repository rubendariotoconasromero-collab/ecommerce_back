<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrderReturn extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_returns';

    // request_at la gestiona MySQL (DEFAULT CURRENT_TIMESTAMP); no hay updated_at
    public $timestamps = false;

    const TYPES = ['full', 'partial'];

    const STATUSES = ['requested', 'approved', 'rejected', 'resolved'];

    // Estados que bloquean nuevas solicitudes de devolución en la misma orden
    const BLOCKING_STATUSES = ['requested', 'approved'];

    // Transiciones permitidas
    const TRANSITIONS = [
        'requested' => ['approved', 'rejected'],
        'approved'  => ['resolved'],
        'rejected'  => [],
        'resolved'  => [],
    ];

    protected $fillable = [
        'order_id',
        'order_item_id',
        'return_type',
        'status',
        'reason',
        'refund_amount',
        'resolved_at',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'request_at'    => 'datetime',
        'resolved_at'   => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
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
        return $query->whereIn('status', self::BLOCKING_STATUSES);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // -------------------------------------------------------------------------
    // Computed attributes
    // -------------------------------------------------------------------------

    public function getAllowedTransitionsAttribute(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    public function getCanBeApprovedAttribute(): bool
    {
        return in_array('approved', $this->allowed_transitions);
    }

    public function getCanBeRejectedAttribute(): bool
    {
        return in_array('rejected', $this->allowed_transitions);
    }

    public function getCanBeResolvedAttribute(): bool
    {
        return in_array('resolved', $this->allowed_transitions);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowed_transitions);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'customer_id',
        'user_id',
        'order_number',
        'total_amount',
        'status',
        'stock_reserved',
        'expected_delivery_date',
        'shipping_address',
        'notes',
    ];

    protected $casts = [
        'total_amount'           => 'decimal:2',
        'expected_delivery_date' => 'date',
        'stock_reserved'         => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class, 'order_id');
    }

    public function handlers(): HasMany
    {
        return $this->hasMany(OrderHandler::class, 'order_id')->orderBy('handled_at', 'asc');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'order_id')->orderBy('created_at', 'desc');
    }

    // -------------------------------------------------------------------------
    // Atributos computados
    // -------------------------------------------------------------------------

    public function getAmountPaidAttribute(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments
                ->where('status', 'completed')
                ->sum('amount');
        }

        return (float) $this->payments()->where('status', 'completed')->sum('amount');
    }

    public function getAmountPendingAttribute(): float
    {
        return max(0, (float) $this->total_amount - $this->amount_paid);
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->amount_pending <= 0;
    }

    /**
     * Transiciones de estado permitidas desde el estado actual.
     * Incluido en el Resource para que el frontend pueda renderizar los botones correctos.
     */
    public function getAllowedTransitionsAttribute(): array
    {
        return match ($this->status) {
            'pending'       => ['confirmed', 'in_production', 'cancelled'],
            'confirmed'     => ['in_production', 'cancelled'],
            'in_production' => ['ready', 'cancelled'],
            'ready'         => ['shipped', 'cancelled'],
            'shipped'       => ['delivered'],
            'delivered'     => ['returned'],
            default         => [],
        };
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['cancelled', 'returned', 'delivered']);
    }

    public function scopeByStatus(Builder $query, string|array $status): Builder
    {
        return $query->whereIn('status', (array) $status);
    }

    public function scopeForCustomer(Builder $query, string $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isActive(): bool
    {
        return !in_array($this->status, ['cancelled', 'returned', 'delivered']);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowed_transitions);
    }
}

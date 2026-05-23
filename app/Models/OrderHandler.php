<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrderHandler extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_handlers';

    // Desactivamos los timestamps tradicionales de Laravel
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'user_id',
        'handler_name',
        'handler_role',
        'action_taken',
        'notes',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    // RELACIÓN: La gestión pertenece a un pedido
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    // RELACIÓN: La gestión pertenece al usuario del personal que la ejecutó
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

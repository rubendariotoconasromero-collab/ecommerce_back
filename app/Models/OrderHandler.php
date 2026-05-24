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

    // handled_at se gestiona automáticamente por MySQL (useCurrent); no existen created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'user_id',
        'handler_name',
        'handler_role',
        'action_taken',
        'notes',
        // handled_at NO se incluye: MySQL lo asigna con DEFAULT CURRENT_TIMESTAMP
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Customer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'customer_type',
        'name',
        'email',
        'business_name',
        'tax_id',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // RELACIÓN: Un perfil de cliente pertenece opcionalmente a una cuenta de usuario
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // RELACIÓN: Un cliente puede tener muchos pedidos
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }
}

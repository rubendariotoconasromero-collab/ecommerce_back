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

    /**
     * Infiere si un cliente es 'business' o 'individual' a partir de su nombre y NIT.
     * Lógica: sufijos empresariales en el nombre O NIT con ≥9 dígitos → business.
     * NIT boliviano tiene 10+ dígitos; CI tiene 7-8 dígitos.
     */
    public static function inferType(string $name, string $taxId): string
    {
        $businessSuffixes = ['S.A.', 'SRL', 'S.R.L.', 'LTDA', 'S.A.S.', 'CIA.', 'E.I.R.L.', 'S.C.', 'COOP.'];
        $upperName = strtoupper($name);

        foreach ($businessSuffixes as $suffix) {
            if (str_contains($upperName, strtoupper($suffix))) {
                return 'business';
            }
        }

        $digitsOnly = preg_replace('/\D/', '', $taxId);
        if (strlen($digitsOnly) >= 9) {
            return 'business';
        }

        return 'individual';
    }
}

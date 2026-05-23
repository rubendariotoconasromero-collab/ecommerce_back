<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasUuids;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // RELACIÓN: Un usuario pertenece a un rol (1:N)
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // RELACIÓN: Un usuario puede tener un perfil de cliente comercial
    public function customer()
    {
        return $this->hasOne(Customer::class, 'user_id');
    }

    // MÉTODO HELPER: Verifica si el usuario tiene un rol específico por slug
    public function hasRole(string $roleSlug): bool
    {
        return $this->role && $this->role->slug === $roleSlug;
    }

    // MÉTODO HELPER: Verifica si el usuario tiene un permiso específico
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->role && $this->role->permissions()->where('slug', $permissionSlug)->exists();
    }
}

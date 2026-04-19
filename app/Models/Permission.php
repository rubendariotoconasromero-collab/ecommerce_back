<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'module',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // RELACIÓN: Un permiso pertenece a muchos roles
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
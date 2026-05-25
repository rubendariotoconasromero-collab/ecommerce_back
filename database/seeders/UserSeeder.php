<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buscar el rol de Super Admin
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        if (!$superAdminRole) {
            return;
        }

        // 2. Crear el usuario Super Admin
        User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'role_id'   => $superAdminRole->id,
                'name'      => 'Administrador Principal',
                'password'  => 'password123', // El modelo User aplica hash automáticamente en casts o mutador
                'phone'     => '70000000',
                'is_active' => true,
            ]
        );
    }
}
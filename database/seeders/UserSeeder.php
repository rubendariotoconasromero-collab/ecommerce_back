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
        // 1. Crear el usuario Super Admin
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@tuecommerce.com'], // Cambia esto por tu correo real
            [
                'name' => 'Administrador Principal',
                'customer_type' => 'individual',
                'password' => Hash::make('password123'), // Cambia por una contraseña segura
                'phone' => '70000000',
                'is_active' => true,
            ]
        );

        // 2. Buscar el rol de Super Admin
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        // 3. Asignar el rol al usuario (si el rol existe)
        if ($superAdminRole) {
            // Usamos syncWithoutDetaching para no borrar otros roles si corres el seeder varias veces
            $adminUser->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }
    }
}
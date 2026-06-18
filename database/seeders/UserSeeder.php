<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Crea un usuario de staff por cada rol operativo del sistema.
     * Todos los emails siguen el formato nombre@soluplast.com.
     * Idempotente: usa firstOrCreate para no duplicar en re-ejecuciones.
     */
    public function run(): void
    {
        $staff = [
            [
                'role_slug' => 'super-admin',
                'name'      => 'Administrador del Sistema',
                'email'     => 'admin@soluplast.com',
                'password'  => 'admin123',
                'phone'     => '70000001',
            ],
            [
                'role_slug' => 'gerente',
                'name'      => 'Ricardo Montero',
                'email'     => 'gerente@soluplast.com',
                'password'  => 'gerente123',
                'phone'     => '70000002',
            ],
            [
                'role_slug' => 'vendedor',
                'name'      => 'Andrea Vargas',
                'email'     => 'ventas@soluplast.com',
                'password'  => 'ventas123',
                'phone'     => '70000003',
            ],
            [
                'role_slug' => 'operador-inventario',
                'name'      => 'Luis Quispe',
                'email'     => 'inventario@soluplast.com',
                'password'  => 'inv123',
                'phone'     => '70000004',
            ],
            [
                'role_slug' => 'operador-produccion',
                'name'      => 'Marco Flores',
                'email'     => 'produccion@soluplast.com',
                'password'  => 'prod123',
                'phone'     => '70000005',
            ],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($staff as $data) {
            $role = Role::where('slug', $data['role_slug'])->first();

            if (!$role) {
                $this->command->warn("UserSeeder: Rol '{$data['role_slug']}' no encontrado. Ejecuta RoleAndPermissionSeeder primero.");
                $skipped++;
                continue;
            }

            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'role_id'   => $role->id,
                    'name'      => $data['name'],
                    'password'  => $data['password'],
                    'phone'     => $data['phone'],
                    'is_active' => true,
                ]
            );

            if ($user->wasRecentlyCreated) {
                $created++;
            } else {
                $skipped++;
            }
        }

        $this->command->info("UserSeeder: {$created} usuario(s) creado(s), {$skipped} ya existían.");
        $this->command->newLine();
        $this->command->table(
            ['Nombre', 'Email', 'Rol', 'Contraseña'],
            array_map(fn ($u) => [
                $u['name'],
                $u['email'],
                $u['role_slug'],
                $u['password'],
            ], $staff)
        );
    }
}

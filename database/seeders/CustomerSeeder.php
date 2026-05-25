<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Obtener o crear el rol de cliente
        $clienteRole = Role::firstOrCreate(['slug' => 'cliente'], ['name' => 'Cliente']);

        // 2. Definir una lista de clientes para sembrar de manera determinista y profesional
        $customersData = [
            [
                'customer_type' => 'business',
                'name' => 'Alejandro Domínguez',
                'email' => 'ventas@alfa-corporacion.com',
                'business_name' => 'Corporación Alfa S.R.L.',
                'tax_id' => 'NIT-9902011-2',
                'phone' => '+54 11 4433-2211',
                'is_active' => true,
                'create_user' => true, // Generar cuenta de acceso
            ],
            [
                'customer_type' => 'individual',
                'name' => 'Juan Pérez',
                'email' => 'juan.perez@gmail.com',
                'business_name' => null,
                'tax_id' => null,
                'phone' => '+54 11 5566-7788',
                'is_active' => true,
                'create_user' => true, // Generar cuenta de acceso
            ],
            [
                'customer_type' => 'business',
                'name' => 'Mariela Vázquez',
                'email' => 'compras@logistica-nacional.com',
                'business_name' => 'Logística Nacional S.A.',
                'tax_id' => 'NIT-8833910-4',
                'phone' => '+54 11 3322-1100',
                'is_active' => true,
                'create_user' => false, // Cliente sin cuenta de usuario asociada
            ],
            [
                'customer_type' => 'individual',
                'name' => 'Ana Paula Rojas',
                'email' => 'ana.rojas@outlook.com',
                'business_name' => null,
                'tax_id' => null,
                'phone' => '+54 11 9900-8877',
                'is_active' => true,
                'create_user' => true,
            ],
            [
                'customer_type' => 'individual',
                'name' => 'Carlos Mendoza',
                'email' => 'carlos.mendoza@gmail.com',
                'business_name' => null,
                'tax_id' => null,
                'phone' => '+54 11 8877-6655',
                'is_active' => false, // Cliente inactivo/suspendido
                'create_user' => false,
            ]
        ];

        foreach ($customersData as $data) {
            $userId = null;

            // Si se requiere crear una cuenta de usuario de acceso para el cliente
            if ($data['create_user']) {
                $user = User::firstOrCreate(
                    ['email' => $data['email']],
                    [
                        'role_id'   => $clienteRole->id,
                        'name'      => $data['name'],
                        'password'  => 'cliente123', // El modelo User aplica hash automáticamente en casts
                        'phone'     => $data['phone'],
                        'is_active' => $data['is_active'],
                    ]
                );
                $userId = $user->id;
            }

            // Crear el perfil del cliente
            Customer::firstOrCreate(
                ['email' => $data['email']],
                [
                    'user_id'       => $userId,
                    'customer_type' => $data['customer_type'],
                    'name'          => $data['name'],
                    'business_name' => $data['business_name'],
                    'tax_id'        => $data['tax_id'],
                    'phone'         => $data['phone'],
                    'is_active'     => $data['is_active'],
                ]
            );
        }
    }
}

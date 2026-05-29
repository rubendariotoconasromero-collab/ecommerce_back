<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. Roles y Permisos (Base de seguridad y control de acceso)
            RoleAndPermissionSeeder::class,

            // 2. Usuarios de Staff/Administración (Personal interno)
            UserSeeder::class,

            // 3. Clientes y sus cuentas de usuario (Acceso comercial externo)
            CustomerSeeder::class,

            // 4. Configuración general de la Empresa
            CompanySettingSeeder::class,

            // 5. Categorías del catálogo de productos
            CategorySeeder::class,

            // 6. Catálogo de Productos y sus fotos
            ProductSeeder::class,

            // 7. Inventario y stock inicial de productos (Depende de que existan los productos)
            InventorySeeder::class,
        ]);
    }
}

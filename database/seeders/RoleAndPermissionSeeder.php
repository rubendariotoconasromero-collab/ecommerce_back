<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // =====================================================================
        // 1. PERMISOS — uno por módulo del panel de administración
        // =====================================================================
        $permissions = [
            // Operaciones
            ['name' => 'Acceso al Módulo Pedidos',    'slug' => 'modulo-pedidos',       'module' => 'Operaciones'],
            ['name' => 'Acceso al Módulo Clientes',   'slug' => 'modulo-clientes',      'module' => 'Operaciones'],
            ['name' => 'Acceso al Módulo Inventario', 'slug' => 'modulo-inventario',    'module' => 'Operaciones'],

            // Catálogo
            ['name' => 'Acceso al Módulo Categorías', 'slug' => 'modulo-categorias',    'module' => 'Catálogo'],
            ['name' => 'Acceso al Módulo Productos',  'slug' => 'modulo-productos',     'module' => 'Catálogo'],

            // Administración
            ['name' => 'Acceso al Módulo Usuarios',       'slug' => 'modulo-usuarios',      'module' => 'Administración'],
            ['name' => 'Acceso al Módulo Roles',          'slug' => 'modulo-roles',         'module' => 'Administración'],
            ['name' => 'Acceso al Módulo Configuración',  'slug' => 'modulo-configuracion', 'module' => 'Administración'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
        }

        $this->command->info('RoleAndPermissionSeeder: ' . count($permissions) . ' permisos verificados.');

        // =====================================================================
        // 2. ROLES
        // =====================================================================

        // — Super Administrador —
        // Acceso total. Único rol que puede gestionar roles y permisos del sistema.
        $superAdmin = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Administrador', 'description' => 'Acceso completo al sistema. Reservado para IT y propietario.']
        );
        $superAdmin->permissions()->sync(Permission::all()->pluck('id'));

        // — Gerente de Operaciones —
        // Supervisión general del negocio: ve y gestiona todo lo operativo y
        // puede administrar usuarios y configuración. NO gestiona roles del sistema.
        $gerente = Role::firstOrCreate(
            ['slug' => 'gerente'],
            ['name' => 'Gerente de Operaciones', 'description' => 'Supervisión operativa completa. Gestiona usuarios y configuración, pero no puede modificar roles del sistema.']
        );
        $gerentePerms = Permission::whereIn('slug', [
            'modulo-pedidos',
            'modulo-clientes',
            'modulo-inventario',
            'modulo-categorias',
            'modulo-productos',
            'modulo-usuarios',
            'modulo-configuracion',
        ])->pluck('id');
        $gerente->permissions()->sync($gerentePerms);

        // — Gestor de Ventas —
        // Gestiona el ciclo comercial completo: pedidos, clientes y catálogo.
        // Puede consultar inventario para verificar disponibilidad antes de confirmar.
        // NO puede gestionar usuarios ni configuración del sistema.
        $vendedor = Role::firstOrCreate(
            ['slug' => 'vendedor'],
            ['name' => 'Gestor de Ventas', 'description' => 'Gestión del ciclo comercial: pedidos, clientes y catálogo de productos.']
        );
        $vendedorPerms = Permission::whereIn('slug', [
            'modulo-pedidos',
            'modulo-clientes',
            'modulo-productos',
            'modulo-categorias',
            'modulo-inventario',
        ])->pluck('id');
        $vendedor->permissions()->sync($vendedorPerms);

        // — Operador de Almacén —
        // Personal de bodega. Solo accede al módulo de inventario para registrar
        // entradas, salidas y ajustes de stock. No ve pedidos ni clientes.
        $opInventario = Role::firstOrCreate(
            ['slug' => 'operador-inventario'],
            ['name' => 'Operador de Almacén', 'description' => 'Gestión exclusiva de inventario: entradas, salidas y ajustes de stock.']
        );
        $opInventario->permissions()->sync(
            Permission::where('slug', 'modulo-inventario')->pluck('id')
        );

        // — Operador de Producción —
        // Operario de planta. Accede al módulo de pedidos únicamente para
        // gestionar y avanzar las órdenes de producción asignadas.
        $opProduccion = Role::firstOrCreate(
            ['slug' => 'operador-produccion'],
            ['name' => 'Operador de Producción', 'description' => 'Gestión de órdenes de producción dentro del módulo de pedidos.']
        );
        $opProduccion->permissions()->sync(
            Permission::where('slug', 'modulo-pedidos')->pluck('id')
        );

        // — Cliente —
        // Perfil externo. No tiene permisos sobre el panel de administración.
        // Solo se usa para vincular una cuenta de acceso a un Customer del storefront.
        Role::firstOrCreate(
            ['slug' => 'cliente'],
            ['name' => 'Cliente', 'description' => 'Perfil externo sin acceso al panel de administración.']
        );

        $this->command->info('RoleAndPermissionSeeder: 6 roles configurados correctamente.');
        $this->command->table(
            ['Rol', 'Permisos asignados'],
            [
                ['Super Administrador',    'Todos (8)'],
                ['Gerente de Operaciones', 'pedidos, clientes, inventario, categorías, productos, usuarios, configuración (7)'],
                ['Gestor de Ventas',       'pedidos, clientes, productos, categorías, inventario (5)'],
                ['Operador de Almacén',    'inventario (1)'],
                ['Operador de Producción', 'pedidos (1)'],
                ['Cliente',               'Ninguno (acceso solo al storefront público)'],
            ]
        );
    }
}

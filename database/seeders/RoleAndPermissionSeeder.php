<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Definir los Permisos por Módulo
        $permissions = [
            // Módulo Usuarios y Accesos
            ['name' => 'Ver Usuarios', 'slug' => 'ver-usuarios', 'module' => 'Usuarios'],
            ['name' => 'Crear Usuarios', 'slug' => 'crear-usuarios', 'module' => 'Usuarios'],
            ['name' => 'Editar Usuarios', 'slug' => 'editar-usuarios', 'module' => 'Usuarios'],
            ['name' => 'Eliminar Usuarios', 'slug' => 'eliminar-usuarios', 'module' => 'Usuarios'],
            
            // Módulo Catálogo
            ['name' => 'Ver Productos', 'slug' => 'ver-productos', 'module' => 'Catálogo'],
            ['name' => 'Crear Productos', 'slug' => 'crear-productos', 'module' => 'Catálogo'],
            ['name' => 'Editar Productos', 'slug' => 'editar-productos', 'module' => 'Catálogo'],
            
            // Módulo Pedidos y Producción (Vital para Make-to-Order)
            ['name' => 'Ver Pedidos', 'slug' => 'ver-pedidos', 'module' => 'Ventas'],
            ['name' => 'Aprobar Pedidos', 'slug' => 'aprobar-pedidos', 'module' => 'Ventas'],
            ['name' => 'Gestionar Producción', 'slug' => 'gestionar-produccion', 'module' => 'Producción'],
            ['name' => 'Control de Calidad', 'slug' => 'control-calidad', 'module' => 'Producción'],
            
            // Módulo Logística
            ['name' => 'Despachar Pedidos', 'slug' => 'despachar-pedidos', 'module' => 'Logística'],
        ];

        // Insertar permisos en la base de datos
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
        }

        // 2. Crear Roles Base
        $roleSuperAdmin = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador']);
        $roleVentas = Role::firstOrCreate(['slug' => 'vendedor'], ['name' => 'Gestor de Ventas']);
        $roleProduccion = Role::firstOrCreate(['slug' => 'operario-produccion'], ['name' => 'Operario de Producción']);
        $roleLogistica = Role::firstOrCreate(['slug' => 'despachador'], ['name' => 'Encargado de Logística']);
        
        // Roles para clientes (B2C y B2B)
        Role::firstOrCreate(['slug' => 'cliente-persona'], ['name' => 'Cliente Individual']);
        Role::firstOrCreate(['slug' => 'cliente-empresa'], ['name' => 'Cliente Corporativo']);

        // 3. Asignar Permisos a los Roles
        
        // El Super Admin obtiene TODOS los permisos de la base de datos
        $allPermissions = Permission::all();
        $roleSuperAdmin->permissions()->sync($allPermissions->pluck('id'));

        // El Vendedor solo ve catálogo, ve pedidos y los aprueba
        $ventasPermissions = Permission::whereIn('slug', ['ver-productos', 'ver-pedidos', 'aprobar-pedidos'])->pluck('id');
        $roleVentas->permissions()->sync($ventasPermissions);

        // El Operario solo gestiona producción y calidad
        $produccionPermissions = Permission::whereIn('slug', ['gestionar-produccion', 'control-calidad'])->pluck('id');
        $roleProduccion->permissions()->sync($produccionPermissions);
    }
}
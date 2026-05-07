<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Definir los Permisos por Módulo (Estructura simplificada)
        $permissions = [
            // Operaciones
            ['name' => 'Acceso al Módulo Pedidos', 'slug' => 'modulo-pedidos', 'module' => 'Operaciones'],
            
            // Catálogo
            ['name' => 'Acceso al Módulo Categorías', 'slug' => 'modulo-categorias', 'module' => 'Catálogo'],
            ['name' => 'Acceso al Módulo Productos', 'slug' => 'modulo-productos', 'module' => 'Catálogo'],
            
            // Administración
            ['name' => 'Acceso al Módulo Usuarios', 'slug' => 'modulo-usuarios', 'module' => 'Administración'],
            ['name' => 'Acceso al Módulo Roles', 'slug' => 'modulo-roles', 'module' => 'Administración'],
            ['name' => 'Acceso al Módulo Configuración', 'slug' => 'modulo-configuracion', 'module' => 'Administración'],
        ];

        // Insertar permisos
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
        }

        // 2. Crear Roles Base
        $roleSuperAdmin = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador']);
        $roleVentas = Role::firstOrCreate(['slug' => 'vendedor'], ['name' => 'Gestor de Ventas']);
        
        // 3. Asignar Permisos
        
        // El Super Admin tiene acceso a TODOS los módulos
        $roleSuperAdmin->permissions()->sync(Permission::all()->pluck('id'));

        // El Vendedor solo accede a Pedidos y Catálogo
        $ventasPerms = Permission::whereIn('slug', [
            'modulo-pedidos', 
            'modulo-categorias', 
            'modulo-productos'
        ])->pluck('id');
        $roleVentas->permissions()->sync($ventasPerms);
    }
}
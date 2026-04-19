<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        // Agrupamos los permisos por el campo 'module' para el Frontend
        // Esto devolverá algo como: { "Ventas": [...], "Catálogo": [...] }
        $permissions = Permission::all()->groupBy('module');
        
        return response()->json($permissions);
    }
}
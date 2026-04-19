<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        // Traemos todos los roles con la cantidad de usuarios que lo tienen
        $roles = Role::withCount('users')->with('permissions:id,name,module')->get();
        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100|unique:roles,name',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        // Autogenerar el slug a partir del nombre (Ej: "Gestor de Ventas" -> "gestor-de-ventas")
        $validated['slug'] = Str::slug($validated['name']);

        $role = Role::create($validated);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json([
            'message' => 'Rol creado exitosamente',
            'role'    => $role->load('permissions')
        ], 201);
    }

    public function show(Role $role)
    {
        return response()->json($role->load('permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100', Rule::unique('roles')->ignore($role->id)],
            'is_active'     => 'boolean',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $role->update($validated);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json([
            'message' => 'Rol actualizado exitosamente',
            'role'    => $role->load('permissions')
        ]);
    }

    public function destroy(Role $role)
    {
        // Protección de seguridad: Evitar borrar el Super Admin
        if ($role->slug === 'super-admin') {
            return response()->json(['message' => 'No puedes eliminar el rol principal'], 403);
        }

        $role->delete();
        return response()->json(['message' => 'Rol eliminado correctamente']);
    }
}
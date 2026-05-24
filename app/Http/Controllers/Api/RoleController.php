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
        $roles = Role::withCount('users')->with('permissions:id,name,module')->get();

        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100|unique:roles,name',
            'description'   => 'nullable|string',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return response()->json([
            'message' => 'Rol creado exitosamente',
            'role'    => $role->load('permissions'),
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
            'description'   => 'nullable|string',
            'is_active'     => 'nullable|boolean',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']),
            'description' => $validated['description'] ?? $role->description,
            'is_active'   => $validated['is_active'] ?? $role->is_active,
        ]);

        if ($request->has('permissions')) {
            $role->permissions()->sync($validated['permissions'] ?? []);
        }

        return response()->json([
            'message' => 'Rol actualizado exitosamente',
            'role'    => $role->load('permissions'),
        ]);
    }

    public function destroy(Role $role)
    {
        if ($role->slug === 'super-admin') {
            return response()->json(['message' => 'No puedes eliminar el rol principal.'], 403);
        }

        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un rol que tiene usuarios asignados.',
            ], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Rol eliminado correctamente']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // 1. Listar Usuarios (Con paginación y búsqueda)
    public function index(Request $request)
    {
        $query = User::with('roles:id,name,slug'); // Traemos los usuarios con sus roles

        // Búsqueda simple para el frontend
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%");
        }

        return response()->json($query->paginate(10));
    }

    // 2. Crear Usuario y Asignar Roles
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_type' => 'required|in:individual,company',
            'name'          => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255|required_if:customer_type,company',
            'tax_id'        => 'nullable|string|max:50',
            'email'         => 'required|string|email|max:255|unique:users',
            'password'      => 'required|string|min:8',
            'phone'         => 'nullable|string|max:20',
            'roles'         => 'nullable|array', // Array de IDs de roles [1, 3]
            'roles.*'       => 'exists:roles,id'
        ]);

        // Hasheamos la contraseña antes de guardar
        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        // Si desde Vue enviamos roles, los asignamos
        if ($request->has('roles')) {
            $user->roles()->sync($request->roles);
        }

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'user'    => $user->load('roles')
        ], 201);
    }

    // 3. Ver un Usuario Específico
    public function show(User $user)
    {
        return response()->json($user->load('roles'));
    }

    // 4. Actualizar Usuario
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'customer_type' => 'required|in:individual,company',
            'name'          => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255|required_if:customer_type,company',
            'tax_id'        => 'nullable|string|max:50',
            'email'         => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone'         => 'nullable|string|max:20',
            'is_active'     => 'boolean',
            'roles'         => 'nullable|array',
            'roles.*'       => 'exists:roles,id'
        ]);

        // Si se envía una nueva contraseña, la actualizamos
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        }

        $user->update($validated);

        // Sincronizar roles (borra los anteriores y pone los nuevos)
        if ($request->has('roles')) {
            $user->roles()->sync($request->roles);
        }

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'user'    => $user->load('roles')
        ]);
    }

    // 5. Eliminar (Borrado Lógico)
    public function destroy(User $user)
    {
        $user->delete(); // Ejecuta SoftDeletes gracias al modelo
        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }
}
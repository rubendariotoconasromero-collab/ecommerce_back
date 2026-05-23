<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // 1. Listar Usuarios (Con paginación, búsqueda y carga de perfil comercial)
    public function index(Request $request)
    {
        $query = User::with(['role:id,name,slug', 'customer']);

        // Búsqueda cruzada inteligente entre usuarios y perfiles de cliente
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('business_name', 'like', "%{$search}%")
                         ->orWhere('tax_id', 'like', "%{$search}%");
                  });
            });
        }

        return response()->json($query->paginate(10));
    }

    // 2. Crear Usuario y Asignar Rol / Perfil Comercial
    public function store(Request $request)
    {
        $validated = $request->validate([
            'role_id'       => 'required|exists:roles,id',
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users',
            'password'      => 'required|string|min:8',
            'phone'         => 'nullable|string|max:30',
            'is_active'     => 'nullable|boolean',
            
            // Campos comerciales para perfil de cliente (opcionales)
            'customer_type' => 'nullable|in:individual,business,company',
            'business_name' => 'nullable|string|max:255',
            'tax_id'        => 'nullable|string|max:50',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // 1. Crear el usuario de acceso
            $user = User::create([
                'role_id'   => $validated['role_id'],
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($validated['password']),
                'phone'     => $validated['phone'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // 2. Si se provee información comercial, se crea el perfil de cliente
            if ($request->has('customer_type')) {
                // Compatibilidad: mapeamos 'company' a 'business' de la DB
                $type = $validated['customer_type'];
                if ($type === 'company') {
                    $type = 'business';
                }

                Customer::create([
                    'user_id'       => $user->id,
                    'customer_type' => $type,
                    'name'          => $validated['name'],
                    'email'         => $validated['email'],
                    'business_name' => $validated['business_name'] ?? null,
                    'tax_id'        => $validated['tax_id'] ?? null,
                    'phone'         => $validated['phone'] ?? null,
                ]);
            }

            return response()->json([
                'message' => 'Usuario y perfil creados exitosamente',
                'user'    => $user->load('role', 'customer')
            ], 201);
        });
    }

    // 3. Ver un Usuario Específico
    public function show(User $user)
    {
        return response()->json($user->load('role', 'customer'));
    }

    // 4. Actualizar Usuario y Perfil Comercial
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'role_id'       => 'nullable|exists:roles,id',
            'name'          => 'required|string|max:255',
            'email'         => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone'         => 'nullable|string|max:30',
            'is_active'     => 'nullable|boolean',
            'password'      => 'nullable|string|min:8',
            
            // Campos comerciales para actualizar
            'customer_type' => 'nullable|in:individual,business,company',
            'business_name' => 'nullable|string|max:255',
            'tax_id'        => 'nullable|string|max:50',
        ]);

        return DB::transaction(function () use ($validated, $request, $user) {
            // 1. Actualizar datos de usuario
            $userData = [
                'name'  => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
            ];

            if ($request->has('role_id')) {
                $userData['role_id'] = $validated['role_id'];
            }

            if ($request->has('is_active')) {
                $userData['is_active'] = $validated['is_active'];
            }

            if ($request->filled('password')) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);

            // 2. Si se suministra customer_type, actualizamos o creamos el perfil de cliente
            if ($request->has('customer_type')) {
                $type = $validated['customer_type'];
                if ($type === 'company') {
                    $type = 'business';
                }

                $user->customer()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'customer_type' => $type,
                        'name'          => $validated['name'],
                        'email'         => $validated['email'],
                        'business_name' => $validated['business_name'] ?? null,
                        'tax_id'        => $validated['tax_id'] ?? null,
                        'phone'         => $validated['phone'] ?? null,
                    ]
                );
            }

            return response()->json([
                'message' => 'Usuario y perfil comercial actualizados exitosamente',
                'user'    => $user->load('role', 'customer')
            ]);
        });
    }

    // 5. Eliminar (Borrado Lógico)
    public function destroy(User $user)
    {
        $user->delete(); // Ejecuta SoftDeletes nativo de Laravel
        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }
}
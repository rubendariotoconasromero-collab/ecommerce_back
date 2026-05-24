<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['role:id,name,slug', 'customer']);

        if ($request->filled('search')) {
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

        return UserResource::collection($query->paginate(15));
    }

    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'role_id'   => $validated['role_id'],
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($validated['password']),
                'phone'     => $validated['phone'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if ($request->filled('customer_type')) {
                Customer::create([
                    'user_id'       => $user->id,
                    'customer_type' => $validated['customer_type'],
                    'name'          => $validated['name'],
                    'email'         => $validated['email'],
                    'business_name' => $validated['business_name'] ?? null,
                    'tax_id'        => $validated['tax_id'] ?? null,
                    'phone'         => $validated['phone'] ?? null,
                ]);
            }

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'user'    => new UserResource($user->load('role', 'customer')),
            ], 201);
        });
    }

    public function show(User $user)
    {
        return new UserResource($user->load('role', 'customer'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $request, $user) {
            $userData = [
                'name'  => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
            ];

            if ($request->filled('role_id')) {
                $userData['role_id'] = $validated['role_id'];
            }

            if ($request->has('is_active')) {
                $userData['is_active'] = $validated['is_active'];
            }

            if ($request->filled('password')) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);

            if ($request->filled('customer_type')) {
                $user->customer()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'customer_type' => $validated['customer_type'],
                        'name'          => $validated['name'],
                        'email'         => $validated['email'],
                        'business_name' => $validated['business_name'] ?? null,
                        'tax_id'        => $validated['tax_id'] ?? null,
                        'phone'         => $validated['phone'] ?? null,
                    ]
                );
            }

            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'user'    => new UserResource($user->load('role', 'customer')),
            ]);
        });
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta.'], 403);
        }

        if ($user->role && $user->role->slug === 'super-admin') {
            return response()->json(['message' => 'No se puede eliminar al super administrador.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }
}

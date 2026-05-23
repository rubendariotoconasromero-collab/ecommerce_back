<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Iniciar Sesión (Login)
     */
    public function login(Request $request)
    {
        // 1. Validar los datos recibidos desde Vue
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string' // Opcional: para saber desde dónde se loguea
        ]);

        // 2. Intentar autenticar con las credenciales
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // 3. Control de Seguridad: Verificar si el usuario está activo
        if (!$user->is_active) {
            // Si está inactivo, cerramos el intento de sesión y arrojamos error
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta ha sido desactivada. Contacta con administración.'],
            ]);
        }

        // 4. Generar el Token de Sanctum
        // Usamos device_name si se envía, si no, un nombre por defecto
        $tokenName = $request->device_name ?? 'web-app';
        $token = $user->createToken($tokenName)->plainTextToken;

        // 5. Cargar el rol y permisos del usuario para que Vue sepa qué menús mostrar
        $user->load('role.permissions');

        return response()->json([
            'message' => 'Sesión iniciada correctamente',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    /**
     * Cerrar Sesión (Logout)
     */
    public function logout(Request $request)
    {
        // Revoca (elimina) únicamente el token que se usó para esta petición
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ]);
    }

    /**
     * Obtener el Usuario Autenticado (Me)
     * Vital para que Vue recupere la sesión al recargar el navegador (F5)
     */
    public function me(Request $request)
    {
        // Devolvemos el usuario junto con su rol y permisos
        $user = $request->user()->load('role.permissions');
        
        return response()->json($user);
    }
}
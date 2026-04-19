<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;

/*
|--------------------------------------------------------------------------
| Rutas Públicas (No requieren Token)
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);

// Si en el futuro quieres que los clientes se registren por sí mismos en el E-commerce
// Route::post('/register', [AuthController::class, 'register']);


/*
|--------------------------------------------------------------------------
| Rutas Protegidas (Requieren Token de Sanctum válido)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    // Autenticación de la sesión actual
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Módulo de Usuarios y RBAC (Controladores que creamos antes)
    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);
    Route::get('/permissions', [PermissionController::class, 'index']);
    
});
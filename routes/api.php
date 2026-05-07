<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\CompanySettingController;


/*
|--------------------------------------------------------------------------
| Rutas Públicas (No requieren Token)
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);
Route::get('/public/categories', [CategoryController::class, 'index']);
Route::get('/public/settings', [CompanySettingController::class, 'show']);

// Ruta para ver el catálogo público (Se recomienda enviar ?active=true desde Vue)
Route::get('/public/products', [ProductController::class, 'index']);
// Ruta para ver el detalle de un producto público
Route::get('/public/products/{product}', [ProductController::class, 'show']);

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
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);


    // Módulo de Galería de Productos
    Route::get('products/{product}/images', [ProductImageController::class, 'index']);
    Route::post('products/{product}/images', [ProductImageController::class, 'store']);
    Route::put('products/{product}/images/{image}/primary', [ProductImageController::class, 'setPrimary']);
    Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy']);

    // Configuración de la Empresa
    Route::get('/settings', [CompanySettingController::class, 'show']);
    Route::post('/settings', [CompanySettingController::class, 'update']);
    
});
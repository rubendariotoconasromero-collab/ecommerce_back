<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InventoryAdjustmentController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderReturnController;
use App\Http\Controllers\Api\ProductionOrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PublicOrderController;
use App\Http\Controllers\CompanySettingController;

/*
|--------------------------------------------------------------------------
| Rutas Públicas
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);
Route::get('/public/categories', [CategoryController::class, 'index']);
Route::get('/public/settings', [CompanySettingController::class, 'show']);
Route::get('/public/products', [ProductController::class, 'index']);
Route::get('/public/products/{product}', [ProductController::class, 'show']);
Route::post('/public/orders', [PublicOrderController::class, 'store']);

/*
|--------------------------------------------------------------------------
| Rutas Protegidas (requieren token Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // --- Dashboard ---
    Route::get('/dashboard', DashboardController::class);

    // --- Sesión ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // --- RBAC ---
    Route::patch('users/{user}/restore', [UserController::class, 'restore'])->withTrashed();
    Route::apiResource('users', UserController::class);
    Route::patch('roles/{role}/restore', [RoleController::class, 'restore']);
    Route::apiResource('roles', RoleController::class);
    Route::get('/permissions', [PermissionController::class, 'index']);

    // --- Catálogo ---
    Route::patch('categories/{category}/restore', [CategoryController::class, 'restore']);
    Route::apiResource('categories', CategoryController::class);
    Route::patch('products/{product}/restore', [ProductController::class, 'restore']);
    Route::apiResource('products', ProductController::class);

    // Galería de imágenes de producto
    Route::prefix('products/{product}/images')->group(function () {
        Route::get('/', [ProductImageController::class, 'index']);
        Route::post('/', [ProductImageController::class, 'store']);
        Route::put('{image}/primary', [ProductImageController::class, 'setPrimary'])
             ->where('image', '[0-9a-f-]+');
        Route::delete('{image}', [ProductImageController::class, 'destroy'])
             ->where('image', '[0-9a-f-]+');
    });

    // --- Configuración de empresa ---
    Route::get('/settings', [CompanySettingController::class, 'show']);
    Route::post('/settings', [CompanySettingController::class, 'update']);

    // --- Módulo Clientes ---
    Route::apiResource('customers', CustomerController::class);
    Route::patch('customers/{customer}/restore', [CustomerController::class, 'restore']);

    // --- Módulo Inventario ---
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::get('/inventory/{product}', [InventoryController::class, 'show']);
    Route::patch('/inventory/{product}/reorder-point', [InventoryController::class, 'updateReorderPoint']);

    // --- Ajustes de Stock (lotes multi-producto) ---
    Route::get('/inventory-adjustments', [InventoryAdjustmentController::class, 'index']);
    Route::post('/inventory-adjustments', [InventoryAdjustmentController::class, 'store']);
    Route::get('/inventory-adjustments/{batch}', [InventoryAdjustmentController::class, 'show']);

    // --- Módulo Órdenes ---
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/handlers', [OrderController::class, 'handlers']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'changeStatus']);
    Route::delete('/orders/{order}', [OrderController::class, 'destroy']);

    // --- Módulo Producción ---
    Route::get('/production-orders', [ProductionOrderController::class, 'index']);
    Route::prefix('orders/{order}/production-orders')->group(function () {
        Route::get('/', [ProductionOrderController::class, 'indexForOrder']);
        Route::post('/', [ProductionOrderController::class, 'store']);
        Route::get('{prodOrder}', [ProductionOrderController::class, 'show'])
             ->where('prodOrder', '[0-9a-f-]+');
        Route::patch('{prodOrder}/start', [ProductionOrderController::class, 'start'])
             ->where('prodOrder', '[0-9a-f-]+');
        Route::patch('{prodOrder}/complete', [ProductionOrderController::class, 'complete'])
             ->where('prodOrder', '[0-9a-f-]+');
        Route::patch('{prodOrder}/cancel', [ProductionOrderController::class, 'cancel'])
             ->where('prodOrder', '[0-9a-f-]+');
        Route::patch('{prodOrder}/assign', [ProductionOrderController::class, 'assign'])
             ->where('prodOrder', '[0-9a-f-]+');
    });

    // --- Módulo Logística & Envíos ---
    Route::get('/shipments', [ShipmentController::class, 'index']);
    Route::prefix('orders/{order}/shipments')->group(function () {
        Route::get('/', [ShipmentController::class, 'indexForOrder']);
        Route::post('/', [ShipmentController::class, 'store']);
        Route::get('{shipment}', [ShipmentController::class, 'show'])
             ->where('shipment', '[0-9a-f-]+');
        Route::patch('{shipment}/dispatch', [ShipmentController::class, 'dispatch'])
             ->where('shipment', '[0-9a-f-]+');
        Route::patch('{shipment}/in-transit', [ShipmentController::class, 'inTransit'])
             ->where('shipment', '[0-9a-f-]+');
        Route::patch('{shipment}/deliver', [ShipmentController::class, 'deliver'])
             ->where('shipment', '[0-9a-f-]+');
        Route::patch('{shipment}/fail', [ShipmentController::class, 'fail'])
             ->where('shipment', '[0-9a-f-]+');
    });

    // --- Módulo Devoluciones ---
    Route::get('/returns', [OrderReturnController::class, 'index']);
    Route::prefix('orders/{order}/returns')->group(function () {
        Route::get('/', [OrderReturnController::class, 'indexForOrder']);
        Route::post('/', [OrderReturnController::class, 'store']);
        Route::get('{return}', [OrderReturnController::class, 'show'])
             ->where('return', '[0-9a-f-]+');
        Route::patch('{return}/approve', [OrderReturnController::class, 'approve'])
             ->where('return', '[0-9a-f-]+');
        Route::patch('{return}/reject', [OrderReturnController::class, 'reject'])
             ->where('return', '[0-9a-f-]+');
        Route::patch('{return}/resolve', [OrderReturnController::class, 'resolve'])
             ->where('return', '[0-9a-f-]+');
    });

    // --- Módulo Pagos ---
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::prefix('orders/{order}/payments')->group(function () {
        Route::get('/', [PaymentController::class, 'indexForOrder']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::patch('{payment}/complete', [PaymentController::class, 'complete'])
             ->where('payment', '[0-9a-f-]+');
        Route::patch('{payment}/fail', [PaymentController::class, 'fail'])
             ->where('payment', '[0-9a-f-]+');
        Route::patch('{payment}/refund', [PaymentController::class, 'refund'])
             ->where('payment', '[0-9a-f-]+');
    });
});

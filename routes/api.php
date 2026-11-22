<?php

use App\Http\Controllers\Api\AdminBrandController;
use App\Http\Controllers\Api\AdminCustomerController;
use App\Http\Controllers\Api\AdminInventoryController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleManagementController;
use App\Http\Controllers\Api\StoreCustomerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/brand-logos/{filename}', [AdminBrandController::class, 'showLogo'])
        ->where('filename', '[A-Za-z0-9]{40}\.(png|jpe?g|webp)')
        ->name('brand-logos.show');
    Route::get('/catalog-images/{filename}', [AdminInventoryController::class, 'showCatalogImage'])
        ->where('filename', '[A-Za-z0-9]{40}\.(png|jpe?g|webp)')
        ->name('catalog-images.show');

    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

        Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
            Route::get('/me', [AuthController::class, 'currentUser']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::prefix('store')->group(function (): void {
        Route::post('/customers/register', [StoreCustomerController::class, 'register'])->middleware('throttle:15,1');
        Route::post('/customers/lookup', [StoreCustomerController::class, 'lookup'])->middleware('throttle:30,1');
    });

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('admin')->group(function (): void {
        Route::get('/roles', [RoleManagementController::class, 'index']);
        Route::post('/roles', [RoleManagementController::class, 'store']);
        Route::put('/roles/{slug}', [RoleManagementController::class, 'update']);
        Route::delete('/roles/{slug}', [RoleManagementController::class, 'destroy']);

        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::patch('/users/{user}/role', [AdminUserController::class, 'assignRole']);
        Route::patch('/users/{user}/status', [AdminUserController::class, 'setStatus']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);

        Route::get('/customers', [AdminCustomerController::class, 'index']);
        Route::post('/customers', [AdminCustomerController::class, 'store']);
        Route::post('/customers/bulk-import', [AdminCustomerController::class, 'bulkImport']);
        Route::post('/customers/bulk-delete', [AdminCustomerController::class, 'bulkDelete']);
        Route::get('/customers/{customer}', [AdminCustomerController::class, 'show']);
        Route::put('/customers/{customer}', [AdminCustomerController::class, 'update']);
        Route::patch('/customers/{customer}/status', [AdminCustomerController::class, 'setStatus']);
        Route::delete('/customers/{customer}', [AdminCustomerController::class, 'destroy']);

        Route::get('/reports/summary', [AdminReportController::class, 'summary']);

        // ── Brands & Models ──────────────────────────────────────────────────
        Route::get('/brands', [AdminBrandController::class, 'index']);
        Route::post('/brands', [AdminBrandController::class, 'store']);
        Route::put('/brands/{brand}', [AdminBrandController::class, 'update']);
        Route::delete('/brands/{brand}', [AdminBrandController::class, 'destroy']);
        Route::get('/brands/{brand}/models', [AdminBrandController::class, 'models']);
        Route::post('/brands/{brand}/models', [AdminBrandController::class, 'storeModel']);
        Route::put('/models/{model}', [AdminBrandController::class, 'updateModel']);
        Route::delete('/models/{model}', [AdminBrandController::class, 'destroyModel']);

        // ── Spare Parts Inventory ────────────────────────────────────────────
        Route::get('/inventory', [AdminInventoryController::class, 'index']);
        Route::post('/inventory/appliance-types', [AdminInventoryController::class, 'storeApplianceType']);
        Route::put('/inventory/appliance-types/{applianceType}', [AdminInventoryController::class, 'updateApplianceType']);
        Route::delete('/inventory/appliance-types/{applianceType}', [AdminInventoryController::class, 'destroyApplianceType']);
        Route::post('/inventory/part-categories', [AdminInventoryController::class, 'storePartCategory']);
        Route::post('/inventory', [AdminInventoryController::class, 'store']);
        Route::get('/inventory/{part}', [AdminInventoryController::class, 'show']);
        Route::put('/inventory/{part}', [AdminInventoryController::class, 'update']);
        Route::patch('/inventory/{part}/stock', [AdminInventoryController::class, 'updateStock']);
        Route::delete('/inventory/{part}', [AdminInventoryController::class, 'destroy']);
    });
});

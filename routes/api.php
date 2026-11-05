<?php

use App\Http\Controllers\Api\AdminCustomerController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleManagementController;
use App\Http\Controllers\Api\StoreCustomerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
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
    });
});

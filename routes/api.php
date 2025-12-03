<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\VehicleTypeController;
use App\Http\Controllers\Api\VehicleTypeFieldController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehicleDocumentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public API routes
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Cranelift SaaS API is running',
        'timestamp' => now()->toISOString(),
    ]);
});

// Tenant/Business Registration
Route::post('/tenants/register', [TenantController::class, 'registerBusiness']);

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/google/redirect', [AuthController::class, 'googleRedirect']);
    Route::get('/google/callback', [AuthController::class, 'googleCallback']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Tenant Management
    Route::get('/tenant', [TenantController::class, 'getCurrentTenant']);
    Route::put('/tenant', [TenantController::class, 'updateTenant']);

    // Vehicle Types (Global - read-only for tenants, managed by superadmin in Nova)
    Route::get('/vehicle-types', [VehicleTypeController::class, 'index']);
    Route::get('/vehicle-types/{id}', [VehicleTypeController::class, 'show']);
    Route::get('/vehicle-types/{id}/fields', [VehicleTypeController::class, 'fields']);

    // Vehicle Type Fields (Custom fields management for tenants)
    Route::apiResource('vehicle-type-fields', VehicleTypeFieldController::class);

    // Vehicles (Full CRUD with dynamic field values)
    Route::apiResource('vehicles', VehicleController::class);

    // Vehicle Documents
    Route::apiResource('vehicles.documents', VehicleDocumentController::class);
    Route::get('/documents', [VehicleDocumentController::class, 'index']);
});

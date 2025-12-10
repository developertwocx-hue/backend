<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\VehicleTypeController;
use App\Http\Controllers\Api\VehicleTypeFieldController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehicleDocumentController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\VehicleImportController;

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

// Public vehicle access (QR code scanning)
Route::get('/public/vehicles/{token}', [App\Http\Controllers\Api\PublicVehicleController::class, 'show']);

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
        Route::post('/change-password', [AuthController::class, 'changePassword']);
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
    Route::get('/vehicles/stats', [VehicleController::class, 'stats']);
    Route::get('/vehicles/autocomplete/names', [VehicleController::class, 'autocompleteNames']);
    Route::post('/vehicles/bulk-delete', [VehicleController::class, 'bulkDelete']);
    Route::apiResource('vehicles', VehicleController::class);

    // Vehicle Import
    Route::get('/vehicle-types/{id}/import-template', [VehicleImportController::class, 'generateTemplate']);
    Route::post('/vehicles/import/preview', [VehicleImportController::class, 'preview']);
    Route::post('/vehicles/import', [VehicleImportController::class, 'import']);

    // Document Types (Three-level system: Global, Vehicle-Type Specific, Tenant Custom)
    Route::get('/document-types', [DocumentTypeController::class, 'index']);
    Route::get('/document-types/{id}', [DocumentTypeController::class, 'show']);
    Route::post('/document-types', [DocumentTypeController::class, 'store']); // Tenant creates custom
    Route::put('/document-types/{id}', [DocumentTypeController::class, 'update']); // Tenant updates own
    Route::delete('/document-types/{id}', [DocumentTypeController::class, 'destroy']); // Tenant deletes own
    Route::get('/vehicles/{vehicleId}/document-types', [DocumentTypeController::class, 'forVehicle']);

    // Vehicle Documents
    Route::apiResource('vehicles.documents', VehicleDocumentController::class);
    Route::get('/documents', [VehicleDocumentController::class, 'allDocuments']);
    Route::get('/documents/stats', [VehicleDocumentController::class, 'stats']);
    Route::post('/documents/bulk-delete', [VehicleDocumentController::class, 'bulkDelete']);
    Route::get('/documents/autocomplete/names', [VehicleDocumentController::class, 'autocompleteNames']);
    Route::get('/documents/autocomplete/numbers', [VehicleDocumentController::class, 'autocompleteNumbers']);
});

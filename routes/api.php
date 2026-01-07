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
use App\Http\Controllers\Api\ComplianceTypeController;
use App\Http\Controllers\Api\ComplianceRecordController;
use App\Http\Controllers\Api\ComplianceDashboardController;

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
Route::get('/public/tenant/{tenantToken}/vehicles', [App\Http\Controllers\Api\PublicVehicleController::class, 'index']);

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

    // Vehicle Types (Global + Tenant-specific)
    Route::get('/vehicle-types', [VehicleTypeController::class, 'index']);
    Route::post('/vehicle-types', [VehicleTypeController::class, 'store']);
    Route::get('/vehicle-types/{id}', [VehicleTypeController::class, 'show']);
    Route::put('/vehicle-types/{id}', [VehicleTypeController::class, 'update']);
    Route::delete('/vehicle-types/{id}', [VehicleTypeController::class, 'destroy']);
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

    // ============================================
    // COMPLIANCE MANAGEMENT MODULE
    // ============================================

    // Compliance Types (Global + State-Specific + Tenant Custom)
    Route::get('/compliance-types', [ComplianceTypeController::class, 'index']);
    Route::get('/compliance-types/categories', [ComplianceTypeController::class, 'getCategories']);
    Route::get('/compliance-types/{id}', [ComplianceTypeController::class, 'show']);
    Route::post('/compliance-types', [ComplianceTypeController::class, 'store']); // Tenant creates custom
    Route::put('/compliance-types/{id}', [ComplianceTypeController::class, 'update']); // Tenant updates own
    Route::delete('/compliance-types/{id}', [ComplianceTypeController::class, 'destroy']); // Tenant deletes own
    Route::get('/vehicles/{vehicleId}/compliance-types', [ComplianceTypeController::class, 'getForVehicle']);

    // Compliance Records (THE CORE - Where compliance magic happens)
    Route::get('/vehicles/{vehicleId}/compliance-records', [ComplianceRecordController::class, 'index']);
    Route::get('/vehicles/{vehicleId}/compliance-records/{id}', [ComplianceRecordController::class, 'show']);
    Route::post('/vehicles/{vehicleId}/compliance-records', [ComplianceRecordController::class, 'store']);
    Route::put('/vehicles/{vehicleId}/compliance-records/{id}', [ComplianceRecordController::class, 'update']);
    Route::delete('/vehicles/{vehicleId}/compliance-records/{id}', [ComplianceRecordController::class, 'destroy']);
    Route::get('/vehicles/{vehicleId}/compliance-records/{id}/download', [ComplianceRecordController::class, 'downloadDocument']);

    // Compliance Status & Helpers
    Route::get('/vehicles/{vehicleId}/compliance/status', [ComplianceRecordController::class, 'getCurrentStatus']);
    Route::get('/vehicles/{vehicleId}/compliance/requirements/{requirementId}/history', [ComplianceRecordController::class, 'getHistory']);
    Route::post('/vehicles/{vehicleId}/compliance-records/{id}/approve', [ComplianceRecordController::class, 'approve']);

    // Compliance Dashboard - Fleet Statistics & Alerts
    Route::prefix('compliance/dashboard')->group(function () {
        Route::get('/stats', [ComplianceDashboardController::class, 'getStats']);
        Route::get('/fleet-at-risk', [ComplianceDashboardController::class, 'getFleetAtRisk']);
        Route::get('/overdue-items', [ComplianceDashboardController::class, 'getOverdueItems']);
        Route::get('/expiring-soon', [ComplianceDashboardController::class, 'getExpiringSoon']);
        Route::get('/alerts', [ComplianceDashboardController::class, 'getAlerts']);
        Route::post('/alerts/{alertId}/acknowledge', [ComplianceDashboardController::class, 'acknowledgeAlert']);
        Route::get('/summary-by-category', [ComplianceDashboardController::class, 'getSummaryByCategory']);
    });
});

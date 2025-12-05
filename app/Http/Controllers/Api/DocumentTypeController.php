<?php

namespace App\Http\Controllers\Api;

use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DocumentTypeController extends ApiController
{
    /**
     * Get all document types or filter by vehicle type
     * GET /api/document-types?vehicle_type_id=1
     */
    public function index(Request $request)
    {
        $query = DocumentType::where('is_active', true);

        // Filter by vehicle type if provided
        if ($request->has('vehicle_type_id')) {
            $vehicleTypeId = $request->vehicle_type_id;
            $tenantId = $request->user()->tenant_id ?? null;

            // Get document types applicable for this vehicle type and tenant
            $query->forVehicle($vehicleTypeId, $tenantId);
        } else {
            // Just get active types for this tenant
            $tenantId = $request->user()->tenant_id ?? null;

            $query->where(function($q) use ($tenantId) {
                // Global types
                $q->whereNull('tenant_id')
                  // OR tenant custom types
                  ->orWhere('tenant_id', $tenantId);
            });
        }

        $documentTypes = $query->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->successResponse($documentTypes);
    }

    /**
     * Get a single document type
     * GET /api/document-types/{id}
     */
    public function show($id)
    {
        $documentType = DocumentType::findOrFail($id);

        return $this->successResponse($documentType);
    }

    /**
     * Create a new tenant custom document type
     * POST /api/document-types
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
            'is_required' => 'boolean',
            'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        // Create document type for this tenant
        $documentType = DocumentType::create([
            'name' => $request->name,
            'description' => $request->description,
            'vehicle_type_id' => $request->vehicle_type_id,
            'tenant_id' => $request->user()->tenant_id, // Auto-set tenant
            'is_required' => $request->is_required ?? false,
            'is_active' => true,
            'sort_order' => $request->sort_order ?? 100,
        ]);

        return $this->successResponse(
            $documentType,
            'Document type created successfully',
            201
        );
    }

    /**
     * Update a tenant custom document type
     * PUT /api/document-types/{id}
     */
    public function update(Request $request, $id)
    {
        $documentType = DocumentType::findOrFail($id);

        // Check if user owns this document type (tenant custom only)
        if ($documentType->tenant_id !== $request->user()->tenant_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $documentType->update([
            'name' => $request->name,
            'description' => $request->description,
            'vehicle_type_id' => $request->vehicle_type_id,
            'is_required' => $request->is_required ?? $documentType->is_required,
            'is_active' => $request->is_active ?? $documentType->is_active,
            'sort_order' => $request->sort_order ?? $documentType->sort_order,
        ]);

        return $this->successResponse(
            $documentType,
            'Document type updated successfully'
        );
    }

    /**
     * Delete a tenant custom document type
     * DELETE /api/document-types/{id}
     */
    public function destroy(Request $request, $id)
    {
        $documentType = DocumentType::findOrFail($id);

        // Check if user owns this document type
        if ($documentType->tenant_id !== $request->user()->tenant_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Check if it's being used
        if ($documentType->vehicleDocuments()->count() > 0) {
            return $this->errorResponse(
                'Cannot delete document type that is being used by vehicles',
                400
            );
        }

        $documentType->delete();

        return $this->successResponse(null, 'Document type deleted successfully');
    }

    /**
     * Get document types for a specific vehicle
     * GET /api/vehicles/{vehicleId}/document-types
     */
    public function forVehicle($vehicleId)
    {
        $vehicle = \App\Models\Vehicle::findOrFail($vehicleId);

        $documentTypes = DocumentType::forVehicle(
            $vehicle->vehicle_type_id,
            $vehicle->tenant_id
        )->get();

        return $this->successResponse($documentTypes);
    }
}

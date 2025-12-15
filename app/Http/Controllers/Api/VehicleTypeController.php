<?php

namespace App\Http\Controllers\Api;

use App\Models\VehicleType;
use App\Models\VehicleTypeField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleTypeController extends ApiController
{
    /**
     * Get all vehicle types (global + tenant-specific)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get global types (tenant_id = null) AND tenant-specific types
        $vehicleTypes = VehicleType::where('is_active', true)
            ->where(function($query) use ($user) {
                $query->whereNull('tenant_id')
                      ->orWhere('tenant_id', $user->tenant_id);
            })
            ->orderBy('name')
            ->get();

        return $this->successResponse($vehicleTypes, 'Vehicle types retrieved successfully');
    }

    /**
     * Get single vehicle type (global or tenant-owned)
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $vehicleType = VehicleType::where('id', $id)
            ->where(function($query) use ($user) {
                $query->whereNull('tenant_id')
                      ->orWhere('tenant_id', $user->tenant_id);
            })
            ->first();

        if (!$vehicleType) {
            return $this->errorResponse('Vehicle type not found', 404);
        }

        return $this->successResponse($vehicleType, 'Vehicle type retrieved successfully');
    }

    /**
     * Get fields for a vehicle type (default + custom for current tenant)
     */
    public function fields(Request $request, $id)
    {
        $user = $request->user();
        $vehicleType = VehicleType::find($id);

        if (!$vehicleType) {
            return $this->errorResponse('Vehicle type not found', 404);
        }

        $includeCustom = $request->boolean('include_custom', true);

        $query = VehicleTypeField::where('vehicle_type_id', $id)
            ->where('is_active', true);

        if ($includeCustom) {
            // Include both default (tenant_id = null) and custom (tenant_id = current tenant)
            $query->where(function($q) use ($user) {
                $q->whereNull('tenant_id')
                  ->orWhere('tenant_id', $user->tenant_id);
            });
        } else {
            // Only default fields
            $query->whereNull('tenant_id');
        }

        $fields = $query->orderBy('sort_order')->get();

        return $this->successResponse($fields, 'Vehicle type fields retrieved successfully');
    }

    /**
     * Create vehicle type (tenant-scoped)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $vehicleType = VehicleType::create([
                'tenant_id' => $user->tenant_id,
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->is_active ?? true,
            ]);

            return $this->successResponse($vehicleType, 'Vehicle type created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create vehicle type: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update vehicle type (tenant-owned only)
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        // Find the vehicle type and ensure it belongs to the current tenant
        $vehicleType = VehicleType::where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$vehicleType) {
            return $this->errorResponse('Vehicle type not found or you do not have permission to update it', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $vehicleType->update($request->only(['name', 'description', 'is_active']));

            return $this->successResponse($vehicleType, 'Vehicle type updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update vehicle type: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete vehicle type (tenant-owned only)
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        // Find the vehicle type and ensure it belongs to the current tenant
        $vehicleType = VehicleType::where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$vehicleType) {
            return $this->errorResponse('Vehicle type not found or you do not have permission to delete it', 404);
        }

        try {
            $vehicleType->delete();

            return $this->successResponse(null, 'Vehicle type deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete vehicle type: ' . $e->getMessage(), 500);
        }
    }
}

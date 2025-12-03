<?php

namespace App\Http\Controllers\Api;

use App\Models\VehicleType;
use App\Models\VehicleTypeField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleTypeController extends ApiController
{
    /**
     * Get all vehicle types (global - available to all tenants)
     */
    public function index(Request $request)
    {
        $vehicleTypes = VehicleType::where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->successResponse($vehicleTypes, 'Vehicle types retrieved successfully');
    }

    /**
     * Get single vehicle type
     */
    public function show(Request $request, $id)
    {
        $vehicleType = VehicleType::find($id);

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
     * Create vehicle type (superadmin only - via Nova)
     * Regular tenants cannot create vehicle types
     */
    public function store(Request $request)
    {
        return $this->errorResponse('Vehicle types can only be created by superadmin via Nova', 403);
    }

    /**
     * Update vehicle type (superadmin only - via Nova)
     */
    public function update(Request $request, $id)
    {
        return $this->errorResponse('Vehicle types can only be updated by superadmin via Nova', 403);
    }

    /**
     * Delete vehicle type (superadmin only - via Nova)
     */
    public function destroy(Request $request, $id)
    {
        return $this->errorResponse('Vehicle types can only be deleted by superadmin via Nova', 403);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\ComplianceType;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ComplianceTypeController extends ApiController
{
    /**
     * Get all compliance types
     * Optionally filtered for a specific vehicle
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = ComplianceType::with(['vehicleType', 'state'])
            ->where('is_active', true);

        // Filter: Global + Tenant custom types
        $query->where(function($q) use ($user) {
            $q->whereNull('tenant_id')
              ->orWhere('tenant_id', $user->tenant_id);
        });

        // Filter by vehicle (if vehicle_id provided)
        if ($request->has('vehicle_id')) {
            $vehicle = Vehicle::where('tenant_id', $user->tenant_id)
                ->findOrFail($request->vehicle_id);

            // Use the forVehicle scope
            $query = ComplianceType::forVehicle($vehicle);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by state
        if ($request->has('state_code')) {
            $query->where(function($q) use ($request) {
                $q->whereNull('state_code')
                  ->orWhere('state_code', $request->state_code);
            });
        }

        // Filter by vehicle type
        if ($request->has('vehicle_type_id')) {
            $query->where(function($q) use ($request) {
                $q->whereNull('vehicle_type_id')
                  ->orWhere('vehicle_type_id', $request->vehicle_type_id);
            });
        }

        $complianceTypes = $query->orderBy('sort_order')->orderBy('name')->get();

        return $this->successResponse($complianceTypes, 'Compliance types retrieved successfully');
    }

    /**
     * Get single compliance type
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $complianceType = ComplianceType::with(['vehicleType', 'state'])
            ->where('is_active', true)
            ->where(function($q) use ($user) {
                $q->whereNull('tenant_id')
                  ->orWhere('tenant_id', $user->tenant_id);
            })
            ->findOrFail($id);

        return $this->successResponse($complianceType, 'Compliance type retrieved successfully');
    }

    /**
     * Create custom compliance type (tenant-specific)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:roadworthiness,registration,insurance,inspection,other',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
            'renewal_frequency_days' => 'nullable|integer|min:1',
            'requires_document' => 'boolean',
            'accepted_document_types' => 'nullable|array',
            'accepted_document_types.*' => 'exists:document_types,id',
            'is_required' => 'boolean',
            'alert_thresholds' => 'nullable|array',
            'alert_thresholds.*' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        // Tenant custom types cannot have state_code (that's for superadmin only)
        $complianceType = ComplianceType::create([
            'tenant_id' => $user->tenant_id,
            'name' => $request->name,
            'description' => $request->description,
            'category' => $request->category,
            'vehicle_type_id' => $request->vehicle_type_id,
            'state_code' => null, // Tenants can't create state-specific types
            'renewal_frequency_days' => $request->renewal_frequency_days,
            'requires_document' => $request->boolean('requires_document', true),
            'accepted_document_types' => $request->accepted_document_types,
            'is_required' => $request->boolean('is_required', false),
            'is_active' => true,
            'alert_thresholds' => $request->alert_thresholds ?? [30, 14, 7, 0],
            'sort_order' => 100, // Custom types appear after default ones
        ]);

        $complianceType->load(['vehicleType', 'state']);

        return $this->successResponse($complianceType, 'Custom compliance type created successfully', 201);
    }

    /**
     * Update custom compliance type (tenant can only update their own)
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $complianceType = ComplianceType::where('tenant_id', $user->tenant_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'sometimes|required|in:roadworthiness,registration,insurance,inspection,other',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
            'renewal_frequency_days' => 'nullable|integer|min:1',
            'requires_document' => 'boolean',
            'accepted_document_types' => 'nullable|array',
            'accepted_document_types.*' => 'exists:document_types,id',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'alert_thresholds' => 'nullable|array',
            'alert_thresholds.*' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $complianceType->update($request->only([
            'name',
            'description',
            'category',
            'vehicle_type_id',
            'renewal_frequency_days',
            'requires_document',
            'accepted_document_types',
            'is_required',
            'is_active',
            'alert_thresholds',
        ]));

        $complianceType->load(['vehicleType', 'state']);

        return $this->successResponse($complianceType, 'Compliance type updated successfully');
    }

    /**
     * Delete custom compliance type (tenant can only delete their own)
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $complianceType = ComplianceType::where('tenant_id', $user->tenant_id)
            ->findOrFail($id);

        // Check if any vehicles are using this compliance type
        $usageCount = $complianceType->requirements()->count();

        if ($usageCount > 0) {
            return $this->errorResponse(
                "Cannot delete compliance type. It is currently assigned to {$usageCount} vehicle(s).",
                400
            );
        }

        $complianceType->delete();

        return $this->successResponse(null, 'Compliance type deleted successfully');
    }

    /**
     * Get compliance types applicable to a specific vehicle
     */
    public function getForVehicle(Request $request, $vehicleId)
    {
        $user = $request->user();

        $vehicle = Vehicle::where('tenant_id', $user->tenant_id)
            ->with('vehicleType')
            ->findOrFail($vehicleId);

        $complianceTypes = ComplianceType::forVehicle($vehicle)->get();

        return $this->successResponse([
            'vehicle' => $vehicle,
            'compliance_types' => $complianceTypes,
        ], 'Compliance types for vehicle retrieved successfully');
    }

    /**
     * Get categories
     */
    public function getCategories()
    {
        $categories = [
            'roadworthiness' => 'Roadworthiness',
            'registration' => 'Registration',
            'insurance' => 'Insurance',
            'inspection' => 'Inspection',
            'other' => 'Other',
        ];

        return $this->successResponse($categories, 'Categories retrieved successfully');
    }
}
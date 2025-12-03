<?php

namespace App\Http\Controllers\Api;

use App\Models\VehicleTypeField;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleTypeFieldController extends ApiController
{
    /**
     * Get all fields (optionally filtered by vehicle type)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = VehicleTypeField::query();

        // Filter by vehicle type if provided
        if ($request->has('vehicle_type_id')) {
            $query->where('vehicle_type_id', $request->vehicle_type_id);
        }

        // Filter: default fields + tenant's custom fields
        $query->where(function($q) use ($user) {
            $q->whereNull('tenant_id')
              ->orWhere('tenant_id', $user->tenant_id);
        });

        $fields = $query->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->successResponse($fields, 'Fields retrieved successfully');
    }

    /**
     * Create custom field (tenant-specific)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
            'name' => 'required|string|max:255',
            'key' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                // Unique within vehicle_type_id + tenant_id combination
                function ($attribute, $value, $fail) use ($request, $user) {
                    $exists = VehicleTypeField::where('vehicle_type_id', $request->vehicle_type_id)
                        ->where('tenant_id', $user->tenant_id)
                        ->where('key', $value)
                        ->exists();

                    if ($exists) {
                        $fail('A custom field with this key already exists for this vehicle type.');
                    }
                },
            ],
            'field_type' => 'required|in:text,number,date,select,boolean,textarea',
            'unit' => 'nullable|string|max:255',
            'options' => 'nullable|array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        // Verify vehicle type exists
        $vehicleType = VehicleType::find($request->vehicle_type_id);
        if (!$vehicleType) {
            return $this->errorResponse('Vehicle type not found', 404);
        }

        // Create custom field (tenant_id will be set automatically)
        $field = VehicleTypeField::create([
            'vehicle_type_id' => $request->vehicle_type_id,
            'tenant_id' => $user->tenant_id, // Custom field for this tenant
            'name' => $request->name,
            'key' => $request->key,
            'field_type' => $request->field_type,
            'unit' => $request->unit,
            'options' => $request->options,
            'is_required' => $request->is_required ?? false,
            'is_active' => $request->is_active ?? true,
            'sort_order' => $request->sort_order ?? 100,
            'description' => $request->description,
        ]);

        return $this->successResponse($field, 'Custom field created successfully', 201);
    }

    /**
     * Get single field
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $field = VehicleTypeField::where('id', $id)
            ->where(function($q) use ($user) {
                $q->whereNull('tenant_id')
                  ->orWhere('tenant_id', $user->tenant_id);
            })
            ->first();

        if (!$field) {
            return $this->errorResponse('Field not found', 404);
        }

        return $this->successResponse($field, 'Field retrieved successfully');
    }

    /**
     * Update custom field (only tenant's own custom fields)
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $field = VehicleTypeField::where('id', $id)
            ->where('tenant_id', $user->tenant_id) // Only custom fields
            ->first();

        if (!$field) {
            return $this->errorResponse('Field not found or you do not have permission to update it', 404);
        }

        // Cannot modify default fields
        if ($field->tenant_id === null) {
            return $this->errorResponse('Cannot modify default fields', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'key' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                function ($attribute, $value, $fail) use ($field, $user) {
                    $exists = VehicleTypeField::where('vehicle_type_id', $field->vehicle_type_id)
                        ->where('tenant_id', $user->tenant_id)
                        ->where('key', $value)
                        ->where('id', '!=', $field->id)
                        ->exists();

                    if ($exists) {
                        $fail('A custom field with this key already exists for this vehicle type.');
                    }
                },
            ],
            'field_type' => 'sometimes|in:text,number,date,select,boolean,textarea',
            'unit' => 'nullable|string|max:255',
            'options' => 'nullable|array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $field->update($request->only([
            'name', 'key', 'field_type', 'unit', 'options',
            'is_required', 'is_active', 'sort_order', 'description'
        ]));

        return $this->successResponse($field, 'Field updated successfully');
    }

    /**
     * Delete custom field (only tenant's own custom fields)
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $field = VehicleTypeField::where('id', $id)
            ->where('tenant_id', $user->tenant_id) // Only custom fields
            ->first();

        if (!$field) {
            return $this->errorResponse('Field not found or you do not have permission to delete it', 404);
        }

        // Cannot delete default fields
        if ($field->tenant_id === null) {
            return $this->errorResponse('Cannot delete default fields', 403);
        }

        $field->delete();

        return $this->successResponse(null, 'Field deleted successfully');
    }
}

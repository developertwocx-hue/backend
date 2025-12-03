<?php

namespace App\Http\Controllers\Api;

use App\Models\Vehicle;
use App\Models\VehicleTypeField;
use App\Models\VehicleFieldValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class VehicleController extends ApiController
{
    /**
     * Get all vehicles with field values
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $includeFieldValues = $request->boolean('include_field_values', true);

        $query = Vehicle::with('vehicleType')
            ->where('tenant_id', $user->tenant_id);

        // Filter by vehicle type if provided
        if ($request->has('vehicle_type_id')) {
            $query->where('vehicle_type_id', $request->vehicle_type_id);
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($includeFieldValues) {
            $query->with(['fieldValues.field']);
        }

        $vehicles = $query->latest()->get();

        return $this->successResponse($vehicles, 'Vehicles retrieved successfully');
    }

    /**
     * Get single vehicle with field values
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $includeFieldValues = $request->boolean('include_field_values', true);

        $query = Vehicle::with(['vehicleType', 'documents'])
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id);

        if ($includeFieldValues) {
            $query->with(['fieldValues.field']);
        }

        $vehicle = $query->first();

        if (!$vehicle) {
            return $this->errorResponse('Vehicle not found', 404);
        }

        return $this->successResponse($vehicle, 'Vehicle retrieved successfully');
    }

    /**
     * Create vehicle with field values
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Validate basic vehicle data
        $validator = Validator::make($request->all(), [
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
            'status' => 'required|in:active,maintenance,inactive,sold',
            'field_values' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        DB::beginTransaction();
        try {
            // Create vehicle
            $vehicle = Vehicle::create([
                'tenant_id' => $user->tenant_id,
                'vehicle_type_id' => $request->vehicle_type_id,
                'status' => $request->status,
            ]);

            // Validate and save field values
            if ($request->has('field_values')) {
                $this->saveFieldValues($vehicle, $request->field_values, $user->tenant_id);
            }

            DB::commit();

            return $this->successResponse(
                $vehicle->load(['vehicleType', 'fieldValues.field']),
                'Vehicle created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create vehicle: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update vehicle with field values
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $vehicle = Vehicle::where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$vehicle) {
            return $this->errorResponse('Vehicle not found', 404);
        }

        // Validate
        $validator = Validator::make($request->all(), [
            'vehicle_type_id' => 'sometimes|exists:vehicle_types,id',
            'status' => 'sometimes|in:active,maintenance,inactive,sold',
            'field_values' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        DB::beginTransaction();
        try {
            // Update basic vehicle data
            if ($request->has('vehicle_type_id')) {
                $vehicle->vehicle_type_id = $request->vehicle_type_id;
            }
            if ($request->has('status')) {
                $vehicle->status = $request->status;
            }
            $vehicle->save();

            // Update field values
            if ($request->has('field_values')) {
                $this->saveFieldValues($vehicle, $request->field_values, $user->tenant_id);
            }

            DB::commit();

            return $this->successResponse(
                $vehicle->load(['vehicleType', 'fieldValues.field']),
                'Vehicle updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update vehicle: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete vehicle
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $vehicle = Vehicle::where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$vehicle) {
            return $this->errorResponse('Vehicle not found', 404);
        }

        $vehicle->delete();

        return $this->successResponse(null, 'Vehicle deleted successfully');
    }

    /**
     * Save field values for a vehicle
     */
    protected function saveFieldValues(Vehicle $vehicle, array $fieldValues, string $tenantId)
    {
        // Get all available fields for this vehicle type (default + custom for this tenant)
        $availableFields = VehicleTypeField::where('vehicle_type_id', $vehicle->vehicle_type_id)
            ->where('is_active', true)
            ->where(function($q) use ($tenantId) {
                $q->whereNull('tenant_id')
                  ->orWhere('tenant_id', $tenantId);
            })
            ->get()
            ->keyBy('key');

        $errors = [];

        foreach ($fieldValues as $fieldKey => $value) {
            if (!isset($availableFields[$fieldKey])) {
                continue; // Skip unknown fields
            }

            $field = $availableFields[$fieldKey];

            // Validate required fields
            if ($field->is_required && ($value === null || $value === '')) {
                $errors[$fieldKey] = ["{$field->name} is required"];
                continue;
            }

            // Validate field type
            if ($value !== null && $value !== '') {
                $validationError = $this->validateFieldValue($field, $value);
                if ($validationError) {
                    $errors[$fieldKey] = [$validationError];
                    continue;
                }
            }

            // Save or update field value
            if ($value !== null && $value !== '') {
                VehicleFieldValue::updateOrCreate(
                    [
                        'vehicle_id' => $vehicle->id,
                        'vehicle_type_field_id' => $field->id,
                    ],
                    [
                        'value' => $value,
                    ]
                );
            }
        }

        if (!empty($errors)) {
            throw new \Exception('Field validation failed: ' . json_encode($errors));
        }
    }

    /**
     * Validate field value based on field type
     */
    protected function validateFieldValue(VehicleTypeField $field, $value): ?string
    {
        switch ($field->field_type) {
            case 'number':
                if (!is_numeric($value)) {
                    return "{$field->name} must be a number";
                }
                break;

            case 'date':
                $date = \DateTime::createFromFormat('Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) {
                    return "{$field->name} must be a valid date (YYYY-MM-DD)";
                }
                break;

            case 'select':
                if ($field->options && !isset($field->options[$value])) {
                    return "{$field->name} must be one of the allowed options";
                }
                break;

            case 'boolean':
                if (!in_array($value, ['0', '1', 0, 1, true, false], true)) {
                    return "{$field->name} must be a boolean value";
                }
                break;
        }

        return null;
    }
}

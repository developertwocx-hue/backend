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
        if ($request->has('vehicle_type_id') && $request->vehicle_type_id) {
            $query->where('vehicle_type_id', $request->vehicle_type_id);
        }

        // Filter by status if provided
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by date range if provided
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($includeFieldValues) {
            $query->with(['fieldValues.field']);
        }

        // Get initial results
        $vehicles = $query->latest()->get();

        // Filter by vehicle name if provided (must be done after loading field values)
        if ($request->has('vehicle_name') && $request->vehicle_name) {
            $searchName = strtolower($request->vehicle_name);

            // Find name field IDs
            $nameFieldIds = VehicleTypeField::where('key', 'name')
                ->where('is_active', true)
                ->where(function($q) use ($user) {
                    $q->whereNull('tenant_id')
                      ->orWhere('tenant_id', $user->tenant_id);
                })
                ->pluck('id');

            // Filter vehicles by name field value
            $vehicles = $vehicles->filter(function($vehicle) use ($nameFieldIds, $searchName) {
                $nameField = $vehicle->fieldValues->first(function($fv) use ($nameFieldIds) {
                    return $nameFieldIds->contains($fv->vehicle_type_field_id);
                });

                if (!$nameField) {
                    return false;
                }

                return stripos($nameField->value, $searchName) !== false;
            })->values();
        }

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
            'status' => 'required|in:active,maintenance,inactive',
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
            'status' => 'sometimes|in:active,maintenance,inactive',
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
     * Bulk delete vehicles
     * POST /api/vehicles/bulk-delete
     */
    public function bulkDelete(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:vehicles,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        // Delete only vehicles that belong to the user's tenant
        $deletedCount = Vehicle::where('tenant_id', $user->tenant_id)
            ->whereIn('id', $request->ids)
            ->delete();

        return $this->successResponse(
            ['deleted_count' => $deletedCount],
            "{$deletedCount} vehicle(s) deleted successfully"
        );
    }

    /**
     * Get vehicle statistics
     * GET /api/vehicles/stats
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        $query = Vehicle::where('tenant_id', $user->tenant_id);

        // Apply same filters as index method
        if ($request->has('vehicle_type_id') && $request->vehicle_type_id) {
            $query->where('vehicle_type_id', $request->vehicle_type_id);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Get all vehicles for name filtering if needed
        if ($request->has('vehicle_name') && $request->vehicle_name) {
            $searchName = strtolower($request->vehicle_name);
            $nameFieldIds = VehicleTypeField::where('key', 'name')
                ->where('is_active', true)
                ->where(function($q) use ($user) {
                    $q->whereNull('tenant_id')
                      ->orWhere('tenant_id', $user->tenant_id);
                })
                ->pluck('id');

            $vehicleIds = VehicleFieldValue::whereIn('vehicle_type_field_id', $nameFieldIds)
                ->where('value', 'ILIKE', '%' . $searchName . '%')
                ->pluck('vehicle_id');

            $query->whereIn('id', $vehicleIds);
        }

        // Calculate stats
        $total = $query->count();
        $active = (clone $query)->where('status', 'active')->count();
        $maintenance = (clone $query)->where('status', 'maintenance')->count();
        $inactive = (clone $query)->where('status', 'inactive')->count();

        return $this->successResponse([
            'total' => $total,
            'active' => $active,
            'maintenance' => $maintenance,
            'inactive' => $inactive,
        ]);
    }

    /**
     * Get autocomplete suggestions for vehicle names
     * GET /api/vehicles/autocomplete/names?query=xxx
     */
    public function autocompleteNames(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $query = $request->input('query', '');

        if (strlen($query) < 2) {
            return $this->successResponse([]);
        }

        // Find the name field IDs for this tenant's vehicle types
        $nameFieldIds = VehicleTypeField::where('key', 'name')
            ->where('is_active', true)
            ->where(function($q) use ($tenantId) {
                $q->whereNull('tenant_id')
                  ->orWhere('tenant_id', $tenantId);
            })
            ->pluck('id');

        // Get vehicle IDs that belong to this tenant
        $vehicleIds = Vehicle::where('tenant_id', $tenantId)->pluck('id');

        // Search for matching vehicle names (ILIKE for case-insensitive search in PostgreSQL)
        $suggestions = VehicleFieldValue::whereIn('vehicle_id', $vehicleIds)
            ->whereIn('vehicle_type_field_id', $nameFieldIds)
            ->where('value', 'ILIKE', '%' . $query . '%')
            ->select('value')
            ->distinct()
            ->limit(10)
            ->pluck('value')
            ->values() // Re-index array to ensure proper JSON array format
            ->toArray();

        return $this->successResponse($suggestions);
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

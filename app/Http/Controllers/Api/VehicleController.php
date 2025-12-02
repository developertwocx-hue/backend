<?php

namespace App\Http\Controllers\Api;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleController extends ApiController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $vehicles = Vehicle::with('vehicleType')
            ->where('tenant_id', $user->tenant_id)
            ->get();

        return $this->successResponse($vehicles, 'Vehicles retrieved successfully');
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
            'name' => 'required|string|max:255',
            'make' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'year' => 'nullable|integer',
            'registration_number' => 'nullable|string|max:255',
            'vin' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'capacity' => 'nullable|numeric',
            'capacity_unit' => 'nullable|string|max:50',
            'specifications' => 'nullable|string',
            'status' => 'nullable|in:active,maintenance,inactive,sold',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric',
            'last_service_date' => 'nullable|date',
            'next_service_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $vehicle = Vehicle::create([
            'tenant_id' => $user->tenant_id,
            ...$request->all(),
        ]);

        return $this->successResponse($vehicle->load('vehicleType'), 'Vehicle created successfully', 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $vehicle = Vehicle::with(['vehicleType', 'documents'])
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$vehicle) {
            return $this->errorResponse('Vehicle not found', 404);
        }

        return $this->successResponse($vehicle, 'Vehicle retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $vehicle = Vehicle::where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$vehicle) {
            return $this->errorResponse('Vehicle not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'vehicle_type_id' => 'sometimes|exists:vehicle_types,id',
            'name' => 'sometimes|string|max:255',
            'make' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'year' => 'nullable|integer',
            'registration_number' => 'nullable|string|max:255',
            'vin' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'capacity' => 'nullable|numeric',
            'capacity_unit' => 'nullable|string|max:50',
            'specifications' => 'nullable|string',
            'status' => 'nullable|in:active,maintenance,inactive,sold',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric',
            'last_service_date' => 'nullable|date',
            'next_service_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $vehicle->update($request->all());

        return $this->successResponse($vehicle->load('vehicleType'), 'Vehicle updated successfully');
    }

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
}

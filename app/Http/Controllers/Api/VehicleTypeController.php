<?php

namespace App\Http\Controllers\Api;

use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleTypeController extends ApiController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $vehicleTypes = VehicleType::where('tenant_id', $user->tenant_id)->get();

        return $this->successResponse($vehicleTypes, 'Vehicle types retrieved successfully');
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return $this->errorResponse('Only admins can create vehicle types', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $vehicleType = VehicleType::create([
            'tenant_id' => $user->tenant_id,
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return $this->successResponse($vehicleType, 'Vehicle type created successfully', 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $vehicleType = VehicleType::where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$vehicleType) {
            return $this->errorResponse('Vehicle type not found', 404);
        }

        return $this->successResponse($vehicleType, 'Vehicle type retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return $this->errorResponse('Only admins can update vehicle types', 403);
        }

        $vehicleType = VehicleType::where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$vehicleType) {
            return $this->errorResponse('Vehicle type not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $vehicleType->update($request->only(['name', 'description', 'is_active']));

        return $this->successResponse($vehicleType, 'Vehicle type updated successfully');
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return $this->errorResponse('Only admins can delete vehicle types', 403);
        }

        $vehicleType = VehicleType::where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (!$vehicleType) {
            return $this->errorResponse('Vehicle type not found', 404);
        }

        $vehicleType->delete();

        return $this->successResponse(null, 'Vehicle type deleted successfully');
    }
}

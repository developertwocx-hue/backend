<?php

namespace App\Http\Controllers\Api;

use App\Models\Vehicle;
use Illuminate\Http\Request;

class PublicVehicleController extends ApiController
{
    /**
     * Get vehicle details by QR code token (public access)
     * GET /api/public/vehicles/{token}
     */
    public function show($token)
    {
        // Find vehicle by qr_code_token
        $vehicle = Vehicle::where('qr_code_token', $token)
            ->with([
                'vehicleType',
                'fieldValues.field',
                'documents.documentType'
            ])
            ->first();

        if (!$vehicle) {
            return $this->errorResponse('Vehicle not found', 404);
        }

        // Transform vehicle data for public view
        $vehicleData = [
            'id' => $vehicle->id,
            'vehicle_type' => $vehicle->vehicleType->name ?? 'Unknown',
            'status' => $vehicle->status,
            'field_values' => $vehicle->fieldValues->map(function ($fv) {
                return [
                    'name' => $fv->field->name,
                    'value' => $fv->value,
                    'unit' => $fv->field->unit,
                ];
            }),
            'documents' => $vehicle->documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'document_type' => $doc->documentType->name ?? $doc->document_type,
                    'document_name' => $doc->document_name,
                    'document_number' => $doc->document_number,
                    'file_path' => $doc->file_path,
                    'expiry_date' => $doc->expiry_date,
                    'is_expired' => $doc->is_expired,
                ];
            }),
        ];

        return $this->successResponse($vehicleData);
    }
}

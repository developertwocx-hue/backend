<?php

namespace App\Http\Controllers\Api;

use App\Models\Vehicle;
use App\Models\Tenant;
use Illuminate\Http\Request;

class PublicVehicleController extends ApiController
{
    /**
     * Get all vehicles for a tenant (public access for QR code grid)
     * GET /api/public/tenant/{tenantToken}/vehicles
     */
    public function index($tenantToken)
    {
        // Find tenant by a public token (you may need to add a public_token field to tenants table)
        // For now, we'll use tenant_id as the token - you should implement proper tenant tokens
        $tenant = Tenant::where('id', $tenantToken)->first();

        if (!$tenant) {
            return $this->errorResponse('Tenant not found', 404);
        }

        // Get all vehicles for this tenant
        $vehicles = Vehicle::where('tenant_id', $tenant->id)
            ->where('status', '!=', 'sold') // Exclude sold vehicles
            ->with(['vehicleType', 'fieldValues.field'])
            ->get();

        // Transform vehicles for public view
        $vehiclesData = $vehicles->map(function ($vehicle) {
            return [
                'id' => $vehicle->id,
                'qr_code_token' => $vehicle->qr_code_token,
                'vehicle_type' => $vehicle->vehicleType->name ?? 'Unknown',
                'status' => $vehicle->status,
                'field_values' => $vehicle->fieldValues->map(function ($fv) {
                    return [
                        'name' => $fv->field->name,
                        'value' => $fv->value,
                        'unit' => $fv->field->unit,
                    ];
                }),
            ];
        });

        return $this->successResponse($vehiclesData);
    }

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
                    'document_type_id' => $doc->document_type_id,
                    'document_type' => $doc->documentType ? [
                        'id' => $doc->documentType->id,
                        'name' => $doc->documentType->name,
                    ] : null,
                    'document_name' => $doc->document_name,
                    'document_number' => $doc->document_number,
                    'file_path' => $doc->file_path,
                    'file_type' => $doc->file_type,
                    'file_size' => $doc->file_size,
                    'issue_date' => $doc->issue_date,
                    'expiry_date' => $doc->expiry_date,
                    'is_expired' => $doc->is_expired,
                ];
            }),
        ];

        return $this->successResponse($vehicleData);
    }
}

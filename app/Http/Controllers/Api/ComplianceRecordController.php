<?php

namespace App\Http\Controllers\Api;

use App\Models\ComplianceRecord;
use App\Models\Vehicle;
use App\Models\VehicleComplianceRequirement;
use App\Models\ComplianceType;
use App\Models\VehicleDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ComplianceRecordController extends ApiController
{
    /**
     * Get all compliance records for a vehicle
     */
    public function index(Request $request, $vehicleId)
    {
        $user = $request->user();

        $vehicle = Vehicle::where('tenant_id', $user->tenant_id)
            ->findOrFail($vehicleId);

        $query = ComplianceRecord::with([
            'complianceType',
            'documents',
            'submittedBy',
            'approvedBy'
        ])->where('vehicle_id', $vehicleId);

        // Filter by compliance type
        if ($request->has('compliance_type_id')) {
            $query->where('compliance_type_id', $request->compliance_type_id);
        }

        // Filter by status (computed)
        if ($request->has('status')) {
            $allRecords = $query->get();
            $filtered = $allRecords->filter(function($record) use ($request) {
                return $record->status === $request->status;
            });
            return $this->successResponse($filtered->values(), 'Compliance records retrieved successfully');
        }

        // Filter current records only
        if ($request->boolean('current_only', false)) {
            $query->where('is_current', true);
        }

        $records = $query->latest()->get();

        return $this->successResponse($records, 'Compliance records retrieved successfully');
    }

    /**
     * Get single compliance record
     */
    public function show(Request $request, $vehicleId, $id)
    {
        $user = $request->user();

        $vehicle = Vehicle::where('tenant_id', $user->tenant_id)
            ->findOrFail($vehicleId);

        $record = ComplianceRecord::with([
            'complianceType',
            'requirement',
            'documents',
            'submittedBy',
            'approvedBy',
            'auditLogs.user'
        ])->where('vehicle_id', $vehicleId)
          ->findOrFail($id);

        return $this->successResponse($record, 'Compliance record retrieved successfully');
    }

    /**
     * Create compliance record
     * THIS IS THE KEY ENDPOINT - WHERE COMPLIANCE MAGIC HAPPENS
     */
    public function store(Request $request, $vehicleId)
    {
        $user = $request->user();

        // Fix: Allow SuperAdmins to access any vehicle
        $query = Vehicle::query();
        if ($user->role !== 'superadmin') {
            $query->where('tenant_id', $user->tenant_id);
        }
        $vehicle = $query->findOrFail($vehicleId);

        $validator = Validator::make($request->all(), [
            'compliance_type_id' => 'required|exists:compliance_types,id',
            'issue_date' => 'required|date',
            'expiry_date' => 'required|date|after:issue_date',
            'inspection_provider' => 'nullable|string|max:255',
            'inspection_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'file' => 'nullable|file|max:102400', // 100MB max
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $complianceType = ComplianceType::findOrFail($request->compliance_type_id);

            // VALIDATION: Check if compliance type is applicable to this vehicle
            $applicableTypes = ComplianceType::forVehicle($vehicle)->pluck('id');
            if (!$applicableTypes->contains($complianceType->id)) {
                return $this->errorResponse(
                    'This compliance type is not applicable to this vehicle',
                    422
                );
            }

            // Get or create vehicle compliance requirement
            $requirement = VehicleComplianceRequirement::firstOrCreate([
                'vehicle_id' => $vehicle->id,
                'compliance_type_id' => $complianceType->id,
            ], [
                'tenant_id' => $vehicle->tenant_id, // Use vehicle's tenant_id
                'is_required' => $complianceType->is_required,
            ]);

            // VALIDATION: Check if documents are required
            if ($complianceType->requires_document && !$request->hasFile('file')) {
                DB::rollBack();
                return $this->errorResponse(
                    'This compliance type requires document evidence',
                    422
                );
            }

            $currentRecordData = [
                'tenant_id' => $vehicle->tenant_id, // Use vehicle's tenant_id
                'vehicle_compliance_requirement_id' => $requirement->id,
                'vehicle_id' => $vehicle->id,
                'compliance_type_id' => $complianceType->id,
                'issue_date' => $request->issue_date,
                'expiry_date' => $request->expiry_date,
                'inspection_provider' => $request->inspection_provider,
                'inspection_number' => $request->inspection_number,
                'notes' => $request->notes,
                'submitted_by' => $user->id,
                'is_current' => true,
            ];

            // Handle file upload directly to ComplianceRecord
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filePath = $file->store('compliance-documents', 'public');

                $currentRecordData['document_path'] = $filePath;
                $currentRecordData['document_name'] = $request->document_name ?? $file->getClientOriginalName();
                $currentRecordData['document_type'] = $file->getMimeType();
                $currentRecordData['document_size'] = $file->getSize();
            }

            // Create compliance record
            $record = ComplianceRecord::create($currentRecordData);

            // Update vehicle compliance score
            $vehicle->refresh();
            $vehicle->updateComplianceScore();

            DB::commit();

            $record->load(['complianceType', 'submittedBy']);

            return $this->successResponse($record, 'Compliance record created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update compliance record
     */
    public function update(Request $request, $vehicleId, $id)
    {
        $user = $request->user();

        // Fix: Allow SuperAdmins to access any vehicle
        $query = Vehicle::query();
        if ($user->role !== 'superadmin') {
            $query->where('tenant_id', $user->tenant_id);
        }
        $vehicle = $query->findOrFail($vehicleId);

        $record = ComplianceRecord::where('vehicle_id', $vehicleId)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'issue_date' => 'sometimes|required|date',
            'expiry_date' => 'sometimes|required|date|after:issue_date',
            'inspection_provider' => 'nullable|string|max:255',
            'inspection_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'file' => 'nullable|file|max:102400', // 100MB max
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $updateData = $request->only([
                'issue_date',
                'expiry_date',
                'inspection_provider',
                'inspection_number',
                'notes',
            ]);

            // Handle file upload directly to ComplianceRecord
            if ($request->hasFile('file')) {
                // Delete old file if exists
                if ($record->document_path && Storage::disk('public')->exists($record->document_path)) {
                    Storage::disk('public')->delete($record->document_path);
                }

                $file = $request->file('file');
                $filePath = $file->store('compliance-documents', 'public');

                $updateData['document_path'] = $filePath;
                $updateData['document_name'] = $request->document_name ?? $file->getClientOriginalName();
                $updateData['document_type'] = $file->getMimeType();
                $updateData['document_size'] = $file->getSize();
            }

            $record->update($updateData);

            // Update vehicle compliance score
            $vehicle->refresh();
            $vehicle->updateComplianceScore();

            DB::commit();

            $record->load(['complianceType', 'submittedBy', 'approvedBy']);

            return $this->successResponse($record, 'Compliance record updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Delete compliance record
     */
    public function destroy(Request $request, $vehicleId, $id)
    {
        $user = $request->user();

        $vehicle = Vehicle::where('tenant_id', $user->tenant_id)
            ->findOrFail($vehicleId);

        $record = ComplianceRecord::where('vehicle_id', $vehicleId)
            ->findOrFail($id);

        DB::beginTransaction();
        try {
            // Delete document file if exists
            if ($record->document_path && Storage::disk('public')->exists($record->document_path)) {
                Storage::disk('public')->delete($record->document_path);
            }

            $record->delete();

            // Update vehicle compliance score
            $vehicle->refresh();
            $vehicle->updateComplianceScore();

            DB::commit();

            return $this->successResponse(null, 'Compliance record deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get current compliance status for vehicle
     */
    public function getCurrentStatus(Request $request, $vehicleId)
    {
        $user = $request->user();

        $vehicle = Vehicle::where('tenant_id', $user->tenant_id)
            ->with(['vehicleType', 'complianceRequirements.complianceType', 'complianceRequirements.currentRecord'])
            ->findOrFail($vehicleId);

        // Get ALL requirements (both required and optional)
        $allRequirements = $vehicle->complianceRequirements()
            ->with(['complianceType', 'currentRecord.documents'])
            ->get();

        // Get only required requirements for status calculation
        $requiredRequirements = $allRequirements->filter(fn($req) => $req->is_required);

        // Map all requirements to detailed status
        $requirementStatuses = $allRequirements->map(function($req) {
            return [
                'requirement_id' => $req->id,
                'compliance_type_id' => $req->compliance_type_id,
                'compliance_type_name' => $req->complianceType->name,
                'category' => $req->complianceType->category,
                'is_required' => $req->is_required,
                'renewal_frequency_days' => $req->complianceType->renewal_frequency_days,
                'requires_document' => $req->complianceType->requires_document,
                'status' => $req->getCurrentStatus(),
                'current_record' => $req->currentRecord,
                'days_until_expiry' => $req->getDaysUntilExpiry(),
                'is_overdue' => $req->isOverdue(),
            ];
        });

        // Separate required and optional
        $required = $requirementStatuses->filter(fn($r) => $r['is_required'])->values();
        $optional = $requirementStatuses->filter(fn($r) => !$r['is_required'])->values();

        return $this->successResponse([
            'vehicle' => [
                'id' => $vehicle->id,
                'vehicle_type_id' => $vehicle->vehicle_type_id,
                'vehicle_type_name' => $vehicle->vehicleType->name ?? null,
                'state_of_operation' => $vehicle->state_of_operation,
                'compliance_status' => $vehicle->compliance_status,
                'compliance_score' => $vehicle->compliance_score,
                'operational_status' => $vehicle->operational_status,
            ],
            'requirements' => [
                'required' => $required,
                'optional' => $optional,
            ],
            'summary' => $vehicle->getComplianceSummary(),
        ], 'Current compliance status retrieved successfully');
    }

    /**
     * Get compliance history for a specific requirement
     */
    public function getHistory(Request $request, $vehicleId, $requirementId)
    {
        $user = $request->user();

        $vehicle = Vehicle::where('tenant_id', $user->tenant_id)
            ->findOrFail($vehicleId);

        $requirement = VehicleComplianceRequirement::where('vehicle_id', $vehicleId)
            ->with('complianceType')
            ->findOrFail($requirementId);

        $history = ComplianceRecord::with(['documents', 'submittedBy', 'approvedBy'])
            ->where('vehicle_compliance_requirement_id', $requirementId)
            ->latest()
            ->get();

        return $this->successResponse([
            'requirement' => $requirement,
            'history' => $history,
        ], 'Compliance history retrieved successfully');
    }

    /**
     * Approve compliance record (for admin/manager)
     */
    public function approve(Request $request, $vehicleId, $id)
    {
        $user = $request->user();

        // Fix: Allow SuperAdmins to access any vehicle
        $query = Vehicle::query();
        if ($user->role !== 'superadmin') {
            $query->where('tenant_id', $user->tenant_id);
        }
        $vehicle = $query->findOrFail($vehicleId);

        $record = ComplianceRecord::where('vehicle_id', $vehicleId)
            ->findOrFail($id);

        // Check if already approved
        if ($record->approved_at) {
            return $this->errorResponse('Compliance record already approved', 400);
        }

        $record->update([
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $record->load(['complianceType', 'documents', 'submittedBy', 'approvedBy']);

        return $this->successResponse($record, 'Compliance record approved successfully');
    }

    /**
     * Download compliance document
     */
    public function downloadDocument(Request $request, $vehicleId, $id)
    {
        $user = $request->user();

        // Fix: Allow SuperAdmins to access any vehicle
        $query = Vehicle::query();
        if ($user->role !== 'superadmin') {
            $query->where('tenant_id', $user->tenant_id);
        }
        $vehicle = $query->findOrFail($vehicleId);

        $record = ComplianceRecord::where('vehicle_id', $vehicleId)
            ->findOrFail($id);

        if (!$record->document_path || !Storage::disk('public')->exists($record->document_path)) {
            return $this->errorResponse('Document not found', 404);
        }

        // Set proper headers with MIME type
        $headers = [
            'Content-Type' => $record->document_type ?? 'application/octet-stream',
        ];

        return Storage::disk('public')->download(
            $record->document_path,
            $record->document_name ?? 'document',
            $headers
        );
    }
}
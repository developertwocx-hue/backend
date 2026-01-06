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

        $vehicle = Vehicle::where('tenant_id', $user->tenant_id)
            ->findOrFail($vehicleId);

        $validator = Validator::make($request->all(), [
            'compliance_type_id' => 'required|exists:compliance_types,id',
            'issue_date' => 'required|date',
            'expiry_date' => 'required|date|after:issue_date',
            'inspection_provider' => 'nullable|string|max:255',
            'inspection_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'document_ids' => 'nullable|array',
            'document_ids.*' => 'exists:vehicle_documents,id',
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
                'tenant_id' => $user->tenant_id,
                'is_required' => $complianceType->is_required,
            ]);

            // Handle file upload if provided
            $documentIds = $request->document_ids ?? [];

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filePath = $file->store('vehicle-documents', 'public');

                $document = VehicleDocument::create([
                    'tenant_id' => $user->tenant_id,
                    'vehicle_id' => $vehicle->id,
                    'document_type_id' => null, // Can be set later
                    'document_type' => $complianceType->category ?? 'compliance_evidence', // Fix: Populate legacy field
                    'document_name' => $request->document_name ?? $file->getClientOriginalName(),
                    'file_path' => $filePath,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => $user->id,
                ]);

                $documentIds[] = $document->id;
            }

            // VALIDATION: Check if documents are required
            if ($complianceType->requires_document && empty($documentIds)) {
                DB::rollBack();
                return $this->errorResponse(
                    'This compliance type requires document evidence',
                    422
                );
            }

            // VALIDATION: Check if document types are accepted
            if (!empty($documentIds)) {
                $documents = VehicleDocument::whereIn('id', $documentIds)
                    ->where('vehicle_id', $vehicle->id)
                    ->get();

                $acceptedTypes = $complianceType->accepted_document_types ?? [];

                foreach ($documents as $doc) {
                    if (!empty($acceptedTypes) && $doc->document_type_id &&
                        !in_array($doc->document_type_id, $acceptedTypes)) {
                        DB::rollBack();
                        return $this->errorResponse(
                            "Document '{$doc->document_name}' type not accepted for this compliance",
                            422
                        );
                    }
                }
            }

            // Create compliance record
            $record = ComplianceRecord::create([
                'tenant_id' => $user->tenant_id,
                'vehicle_compliance_requirement_id' => $requirement->id,
                'vehicle_id' => $vehicle->id,
                'compliance_type_id' => $complianceType->id,
                'issue_date' => $request->issue_date,
                'expiry_date' => $request->expiry_date,
                'inspection_provider' => $request->inspection_provider,
                'inspection_number' => $request->inspection_number,
                'notes' => $request->notes,
                'submitted_by' => $user->id,
                'is_current' => true, // Auto-marks previous records as not current
            ]);

            // Link documents
            if (!empty($documentIds)) {
                foreach ($documentIds as $index => $docId) {
                    DB::table('compliance_record_documents')->insert([
                        'compliance_record_id' => $record->id,
                        'vehicle_document_id' => $docId,
                        'is_primary' => $index === 0, // First document is primary
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Update vehicle compliance score
            $vehicle->refresh();
            $vehicle->updateComplianceScore();

            DB::commit();

            $record->load(['complianceType', 'documents', 'submittedBy']);

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

        $vehicle = Vehicle::where('tenant_id', $user->tenant_id)
            ->findOrFail($vehicleId);

        $record = ComplianceRecord::where('vehicle_id', $vehicleId)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'issue_date' => 'sometimes|required|date',
            'expiry_date' => 'sometimes|required|date|after:issue_date',
            'inspection_provider' => 'nullable|string|max:255',
            'inspection_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'document_ids' => 'nullable|array',
            'document_ids.*' => 'exists:vehicle_documents,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $record->update($request->only([
                'issue_date',
                'expiry_date',
                'inspection_provider',
                'inspection_number',
                'notes',
            ]));

            // Update documents if provided
            if ($request->has('document_ids')) {
                // Remove existing links
                DB::table('compliance_record_documents')
                    ->where('compliance_record_id', $record->id)
                    ->delete();

                // Add new links
                foreach ($request->document_ids as $index => $docId) {
                    DB::table('compliance_record_documents')->insert([
                        'compliance_record_id' => $record->id,
                        'vehicle_document_id' => $docId,
                        'is_primary' => $index === 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Update vehicle compliance score
            $vehicle->refresh();
            $vehicle->updateComplianceScore();

            DB::commit();

            $record->load(['complianceType', 'documents', 'submittedBy', 'approvedBy']);

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
            ->with(['complianceRequirements.complianceType', 'complianceRequirements.currentRecord'])
            ->findOrFail($vehicleId);

        $requirements = $vehicle->complianceRequirements()
            ->where('is_required', true)
            ->with(['complianceType', 'currentRecord.documents'])
            ->get();

        $status = $requirements->map(function($req) {
            return [
                'requirement_id' => $req->id,
                'compliance_type' => $req->complianceType->name,
                'category' => $req->complianceType->category,
                'status' => $req->getCurrentStatus(),
                'current_record' => $req->currentRecord,
                'days_until_expiry' => $req->getDaysUntilExpiry(),
                'is_overdue' => $req->isOverdue(),
            ];
        });

        return $this->successResponse([
            'vehicle' => $vehicle->only(['id', 'compliance_status', 'compliance_score', 'operational_status']),
            'requirements' => $status,
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

        $vehicle = Vehicle::where('tenant_id', $user->tenant_id)
            ->findOrFail($vehicleId);

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
}
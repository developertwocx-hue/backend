<?php

namespace App\Http\Controllers\Api;

use App\Models\VehicleDocument;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VehicleDocumentController extends ApiController
{
    /**
     * Get all documents for a specific vehicle (nested resource)
     * GET /api/vehicles/{vehicle}/documents
     */
    public function index(Request $request, $vehicle)
    {
        $vehicleModel = Vehicle::findOrFail($vehicle);

        // Check tenant ownership
        if ($vehicleModel->tenant_id !== $request->user()->tenant_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $documents = VehicleDocument::where('vehicle_id', $vehicle)
            ->with(['documentType', 'uploadedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($documents);
    }

    /**
     * Get all documents (across all vehicles for the tenant)
     * GET /api/documents
     */
    public function allDocuments(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $documents = VehicleDocument::where('tenant_id', $tenantId)
            ->with(['documentType', 'vehicle', 'uploadedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($documents);
    }

    /**
     * Get a single document
     * GET /api/vehicles/{vehicleId}/documents/{id}
     */
    public function show($vehicleId, $id)
    {
        $document = VehicleDocument::where('vehicle_id', $vehicleId)
            ->where('id', $id)
            ->with(['documentType', 'vehicle', 'uploadedBy'])
            ->firstOrFail();

        // Check tenant ownership
        if ($document->tenant_id !== request()->user()->tenant_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        return $this->successResponse($document);
    }

    /**
     * Upload a new document
     * POST /api/vehicles/{vehicleId}/documents
     */
    public function store(Request $request, $vehicleId)
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        // Check tenant ownership
        if ($vehicle->tenant_id !== $request->user()->tenant_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'document_type_id' => 'required|exists:document_types,id',
            'document_name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:255',
            'file' => 'required|file|max:10240', // 10MB max
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        // Handle file upload
        $file = $request->file('file');
        $filePath = $file->store('vehicle-documents', 'public');
        $fileType = $file->getMimeType();
        $fileSize = $file->getSize();

        // Check if document is expired
        $isExpired = false;
        if ($request->expiry_date) {
            $isExpired = now()->greaterThan($request->expiry_date);
        }

        $document = VehicleDocument::create([
            'tenant_id' => $request->user()->tenant_id,
            'vehicle_id' => $vehicleId,
            'document_type_id' => $request->document_type_id,
            'document_name' => $request->document_name,
            'document_number' => $request->document_number,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'file_size' => $fileSize,
            'issue_date' => $request->issue_date,
            'expiry_date' => $request->expiry_date,
            'is_expired' => $isExpired,
            'notes' => $request->notes,
            'uploaded_by' => $request->user()->id,
        ]);

        $document->load(['documentType', 'uploadedBy']);

        return $this->successResponse(
            $document,
            'Document uploaded successfully',
            201
        );
    }

    /**
     * Update a document
     * PUT /api/vehicles/{vehicleId}/documents/{id}
     */
    public function update(Request $request, $vehicleId, $id)
    {
        $document = VehicleDocument::where('vehicle_id', $vehicleId)
            ->where('id', $id)
            ->firstOrFail();

        // Check tenant ownership
        if ($document->tenant_id !== $request->user()->tenant_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'document_type_id' => 'nullable|exists:document_types,id',
            'document_name' => 'nullable|string|max:255',
            'document_number' => 'nullable|string|max:255',
            'file' => 'nullable|file|max:10240', // 10MB max
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $updateData = [];

        if ($request->has('document_type_id')) {
            $updateData['document_type_id'] = $request->document_type_id;
        }

        if ($request->has('document_name')) {
            $updateData['document_name'] = $request->document_name;
        }

        if ($request->has('document_number')) {
            $updateData['document_number'] = $request->document_number;
        }

        if ($request->has('issue_date')) {
            $updateData['issue_date'] = $request->issue_date;
        }

        if ($request->has('expiry_date')) {
            $updateData['expiry_date'] = $request->expiry_date;
            $updateData['is_expired'] = now()->greaterThan($request->expiry_date);
        }

        if ($request->has('notes')) {
            $updateData['notes'] = $request->notes;
        }

        // Handle file replacement
        if ($request->hasFile('file')) {
            // Delete old file
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }

            $file = $request->file('file');
            $updateData['file_path'] = $file->store('vehicle-documents', 'public');
            $updateData['file_type'] = $file->getMimeType();
            $updateData['file_size'] = $file->getSize();
        }

        $document->update($updateData);
        $document->load(['documentType', 'uploadedBy']);

        return $this->successResponse(
            $document,
            'Document updated successfully'
        );
    }

    /**
     * Delete a document
     * DELETE /api/vehicles/{vehicleId}/documents/{id}
     */
    public function destroy($vehicleId, $id)
    {
        $document = VehicleDocument::where('vehicle_id', $vehicleId)
            ->where('id', $id)
            ->firstOrFail();

        // Check tenant ownership
        if ($document->tenant_id !== request()->user()->tenant_id) {
            return $this->errorResponse('Unauthorized', 403);
        }

        // Delete file from storage
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return $this->successResponse(null, 'Document deleted successfully');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComplianceType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'category',
        'vehicle_type_id',
        'state_code',
        'tenant_id',
        'renewal_frequency_days',
        'requires_document',
        'accepted_document_types',
        'is_required',
        'is_active',
        'alert_thresholds',
        'sort_order',
    ];

    protected $casts = [
        'requires_document' => 'boolean',
        'accepted_document_types' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'alert_thresholds' => 'array',
        'renewal_frequency_days' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $appends = ['scope_type'];

    // Relationships
    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function state()
    {
        return $this->belongsTo(AustralianState::class, 'state_code', 'code');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requirements()
    {
        return $this->hasMany(VehicleComplianceRequirement::class);
    }

    public function records()
    {
        return $this->hasMany(ComplianceRecord::class);
    }

    // Scopes - Following DocumentType pattern
    public function scopeGlobal($query)
    {
        return $query->whereNull('tenant_id')
                     ->whereNull('vehicle_type_id')
                     ->whereNull('state_code');
    }

    public function scopeForVehicle($query, Vehicle $vehicle)
    {
        return $query->where('is_active', true)
            ->where(function ($q) use ($vehicle) {
                // Global (all vehicles)
                $q->where(function ($subQ) {
                    $subQ->whereNull('tenant_id')
                         ->whereNull('vehicle_type_id')
                         ->whereNull('state_code');
                })
                // State-specific
                ->orWhere(function ($subQ) use ($vehicle) {
                    $subQ->whereNull('tenant_id')
                         ->where('state_code', $vehicle->state_of_operation);
                })
                // Vehicle-type specific
                ->orWhere(function ($subQ) use ($vehicle) {
                    $subQ->whereNull('tenant_id')
                         ->where('vehicle_type_id', $vehicle->vehicle_type_id);
                })
                // State + Vehicle Type
                ->orWhere(function ($subQ) use ($vehicle) {
                    $subQ->whereNull('tenant_id')
                         ->where('state_code', $vehicle->state_of_operation)
                         ->where('vehicle_type_id', $vehicle->vehicle_type_id);
                })
                // Tenant custom
                ->orWhere('tenant_id', $vehicle->tenant_id);
            })
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Accessors
    public function getScopeTypeAttribute()
    {
        if (!$this->tenant_id && !$this->vehicle_type_id && !$this->state_code) {
            return 'Global';
        }
        if (!$this->tenant_id && $this->state_code && !$this->vehicle_type_id) {
            return 'State-Specific';
        }
        if (!$this->tenant_id && $this->vehicle_type_id && !$this->state_code) {
            return 'Vehicle-Type Specific';
        }
        if (!$this->tenant_id && $this->state_code && $this->vehicle_type_id) {
            return 'State + Vehicle-Type';
        }
        return 'Tenant Custom';
    }

    // Helper: Get accepted document types as DocumentType models
    public function getAcceptedDocumentTypesModels()
    {
        if (empty($this->accepted_document_types)) {
            return collect();
        }
        return DocumentType::whereIn('id', $this->accepted_document_types)->get();
    }

    // Helper: Check if document type is accepted
    public function acceptsDocumentType($documentTypeId)
    {
        if (empty($this->accepted_document_types)) {
            return true; // Accept any if not specified
        }
        return in_array($documentTypeId, $this->accepted_document_types);
    }

    // Helper: Check if this is a default (superadmin) type
    public function isDefault()
    {
        return $this->tenant_id === null;
    }

    // Helper: Check if this is tenant custom
    public function isCustom()
    {
        return $this->tenant_id !== null;
    }
}
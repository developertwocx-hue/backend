<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'vehicle_type_id',
        'tenant_id',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'scope_type',
    ];

    /**
     * Get the vehicle type this document type belongs to (if vehicle-type specific)
     */
    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class);
    }

    /**
     * Get the tenant this document type belongs to (if tenant custom)
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get all vehicle documents using this type
     */
    public function vehicleDocuments()
    {
        return $this->hasMany(VehicleDocument::class);
    }

    /**
     * Scope: Get global document types (superadmin created, all vehicle types)
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('tenant_id')->whereNull('vehicle_type_id');
    }

    /**
     * Scope: Get vehicle-type specific document types (superadmin created)
     */
    public function scopeVehicleTypeSpecific($query, $vehicleTypeId = null)
    {
        $query = $query->whereNull('tenant_id')->whereNotNull('vehicle_type_id');

        if ($vehicleTypeId) {
            $query->where('vehicle_type_id', $vehicleTypeId);
        }

        return $query;
    }

    /**
     * Scope: Get tenant custom document types
     */
    public function scopeTenantCustom($query, $tenantId = null)
    {
        $query = $query->whereNotNull('tenant_id');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    /**
     * Scope: Get all applicable document types for a vehicle
     * Includes: global + vehicle-type specific + tenant custom
     */
    public function scopeForVehicle($query, $vehicleTypeId, $tenantId)
    {
        return $query->where('is_active', true)
            ->where(function ($q) use ($vehicleTypeId, $tenantId) {
                // Global types (tenant_id = NULL, vehicle_type_id = NULL)
                $q->where(function ($subQ) {
                    $subQ->whereNull('tenant_id')->whereNull('vehicle_type_id');
                })
                // Vehicle-type specific (tenant_id = NULL, vehicle_type_id = X)
                ->orWhere(function ($subQ) use ($vehicleTypeId) {
                    $subQ->whereNull('tenant_id')->where('vehicle_type_id', $vehicleTypeId);
                })
                // Tenant custom global (tenant_id = X, vehicle_type_id = NULL)
                ->orWhere(function ($subQ) use ($tenantId) {
                    $subQ->where('tenant_id', $tenantId)->whereNull('vehicle_type_id');
                })
                // Tenant custom vehicle-type specific (tenant_id = X, vehicle_type_id = Y)
                ->orWhere(function ($subQ) use ($vehicleTypeId, $tenantId) {
                    $subQ->where('tenant_id', $tenantId)->where('vehicle_type_id', $vehicleTypeId);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Get the scope type as a readable string
     */
    public function getScopeTypeAttribute()
    {
        if (!$this->tenant_id && !$this->vehicle_type_id) {
            return 'Global';
        }

        if (!$this->tenant_id && $this->vehicle_type_id) {
            return 'Vehicle-Type Specific';
        }

        if ($this->tenant_id && !$this->vehicle_type_id) {
            return 'Tenant Custom (All Types)';
        }

        return 'Tenant Custom (Type-Specific)';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleTypeField extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_type_id',
        'tenant_id',
        'name',
        'key',
        'field_type',
        'unit',
        'options',
        'is_required',
        'is_active',
        'sort_order',
        'description',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Relationship: Belongs to VehicleType
     */
    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class);
    }

    /**
     * Relationship: Belongs to Tenant (optional)
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Has many field values
     */
    public function fieldValues()
    {
        return $this->hasMany(VehicleFieldValue::class);
    }

    /**
     * Scope: Default fields only (tenant_id = NULL)
     */
    public function scopeDefaultFields($query)
    {
        return $query->whereNull('tenant_id');
    }

    /**
     * Scope: Custom fields for a specific tenant
     */
    public function scopeCustomFields($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: All fields for a tenant (default + custom)
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where(function($q) use ($tenantId) {
            $q->whereNull('tenant_id')
              ->orWhere('tenant_id', $tenantId);
        });
    }

    /**
     * Scope: Active fields only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if this is a default field
     */
    public function isDefault()
    {
        return is_null($this->tenant_id);
    }

    /**
     * Check if this is a custom field
     */
    public function isCustom()
    {
        return !is_null($this->tenant_id);
    }
}

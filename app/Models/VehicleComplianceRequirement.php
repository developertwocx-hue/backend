<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class VehicleComplianceRequirement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'compliance_type_id',
        'is_required',
        'state_override',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    // Relationships
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function complianceType()
    {
        return $this->belongsTo(ComplianceType::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function records()
    {
        return $this->hasMany(ComplianceRecord::class);
    }

    public function currentRecord()
    {
        return $this->hasOne(ComplianceRecord::class)->where('is_current', true);
    }

    // Get current compliance status
    public function getCurrentStatus()
    {
        $current = $this->currentRecord;
        if (!$current) {
            return 'pending';
        }
        return $current->status;
    }

    // Check if requirement is overdue
    public function isOverdue()
    {
        $current = $this->currentRecord;
        if (!$current || !$current->expiry_date) {
            return false;
        }
        // Item is overdue only if expiry date is before today (not including today)
        return $current->expiry_date->startOfDay()->lt(now()->startOfDay());
    }

    // Get days until expiry
    public function getDaysUntilExpiry()
    {
        $current = $this->currentRecord;
        if (!$current || !$current->expiry_date) {
            return null;
        }
        return now()->diffInDays($current->expiry_date, false);
    }
}
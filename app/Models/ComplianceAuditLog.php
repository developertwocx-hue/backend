<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ComplianceAuditLog extends Model
{
    use BelongsToTenant;

    // Immutable - no updates allowed
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'compliance_record_id',
        'vehicle_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function complianceRecord()
    {
        return $this->belongsTo(ComplianceRecord::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Prevent updates and deletes - audit logs are immutable
    protected static function boot()
    {
        parent::boot();

        static::updating(function () {
            return false; // Prevent updates
        });

        static::deleting(function () {
            return false; // Prevent deletes
        });
    }
}
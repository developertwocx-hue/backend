<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ComplianceAlert extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'compliance_record_id',
        'vehicle_id',
        'alert_type',
        'days_until_expiry',
        'status',
        'sent_at',
        'sent_to',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'sent_to' => 'array',
        'days_until_expiry' => 'integer',
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

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    // Mark as acknowledged
    public function acknowledge($userId = null)
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_by' => $userId ?? auth()->id(),
            'acknowledged_at' => now(),
        ]);
    }
}
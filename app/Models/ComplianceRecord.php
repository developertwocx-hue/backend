<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ComplianceRecord extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'vehicle_compliance_requirement_id',
        'vehicle_id',
        'compliance_type_id',
        'issue_date',
        'expiry_date',
        'inspection_provider',
        'inspection_number',
        'notes',
        'document_path',
        'document_name',
        'document_type',
        'document_size',
        'submitted_by',
        'approved_by',
        'approved_at',
        'is_current',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'approved_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    protected $appends = ['status', 'days_until_expiry'];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function complianceType()
    {
        return $this->belongsTo(ComplianceType::class);
    }

    public function requirement()
    {
        return $this->belongsTo(VehicleComplianceRequirement::class, 'vehicle_compliance_requirement_id');
    }

    public function documents()
    {
        return $this->belongsToMany(
            VehicleDocument::class,
            'compliance_record_documents',
            'compliance_record_id',
            'vehicle_document_id'
        )->withPivot('is_primary')->withTimestamps();
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function alerts()
    {
        return $this->hasMany(ComplianceAlert::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(ComplianceAuditLog::class);
    }

    // COMPUTED STATUS (NOT STORED) - THE BRAIN OF THE SYSTEM
    public function getStatusAttribute()
    {
        if (!$this->expiry_date) {
            return 'pending';
        }

        $now = now();
        $daysUntil = $now->diffInDays($this->expiry_date, false);

        if ($daysUntil < 0) {
            return 'expired';
        }
        if ($daysUntil <= 7) {
            return 'at_risk';
        }
        return 'compliant';
    }

    public function getDaysUntilExpiryAttribute()
    {
        if (!$this->expiry_date) {
            return null;
        }
        return now()->diffInDays($this->expiry_date, false);
    }

    // Scopes
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
                     ->where('expiry_date', '<', now());
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->whereNotNull('expiry_date')
                     ->where('expiry_date', '>=', now())
                     ->where('expiry_date', '<=', now()->addDays($days));
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeCompliant($query)
    {
        return $query->whereNotNull('expiry_date')
                     ->where('expiry_date', '>', now()->addDays(7));
    }

    public function scopeAtRisk($query)
    {
        return $query->whereNotNull('expiry_date')
                     ->where('expiry_date', '<=', now()->addDays(7))
                     ->where('expiry_date', '>=', now());
    }

    // Boot - Handle is_current logic
    protected static function boot()
    {
        parent::boot();

        // Auto-mark only one record as current per requirement
        static::creating(function ($record) {
            // Auto-fill tenant_id from Vehicle if missing (more reliable than Auth user)
            if (!$record->tenant_id && $record->vehicle_id) {
                // We need to fetch the vehicle to get the tenant_id
                $vehicle = Vehicle::find($record->vehicle_id);
                if ($vehicle) {
                    $record->tenant_id = $vehicle->tenant_id;
                } elseif (auth()->check()) {
                    // Fallback to auth user if vehicle not found (unlikely)
                    $record->tenant_id = auth()->user()->tenant_id;
                }
            }

            // Auto-fill submitted_by from auth user if missing
            if (!$record->submitted_by && auth()->check()) {
                $record->submitted_by = auth()->id();
            }

            // Auto-fill vehicle_compliance_requirement_id
            if (!$record->vehicle_compliance_requirement_id && $record->vehicle_id && $record->compliance_type_id) {
                // Get compliance type details to know if it requires anything
                $complianceType = ComplianceType::find($record->compliance_type_id);

                if ($complianceType) {
                    $requirement = VehicleComplianceRequirement::firstOrCreate([
                        'vehicle_id' => $record->vehicle_id,
                        'compliance_type_id' => $record->compliance_type_id,
                    ], [
                        'tenant_id' => $record->tenant_id,
                        'is_required' => $complianceType->is_required,
                    ]);

                    $record->vehicle_compliance_requirement_id = $requirement->id;
                }
            }

            if ($record->is_current) {
                static::where('vehicle_compliance_requirement_id', $record->vehicle_compliance_requirement_id)
                    ->update(['is_current' => false]);
            }
        });

        static::updating(function ($record) {
            if ($record->is_current && $record->isDirty('is_current')) {
                static::where('vehicle_compliance_requirement_id', $record->vehicle_compliance_requirement_id)
                    ->where('id', '!=', $record->id)
                    ->update(['is_current' => false]);
            }
        });

        // Log audit trail on create/update/delete
        static::created(function ($record) {
            $record->logAudit('created', null, $record->toArray());
        });

        static::updated(function ($record) {
            $record->logAudit('updated', $record->getOriginal(), $record->getChanges());
        });

        static::deleted(function ($record) {
            $record->logAudit('deleted', $record->toArray(), null);
        });
    }

    // Log audit trail
    public function logAudit($action, $oldValues = null, $newValues = null)
    {
        ComplianceAuditLog::create([
            'tenant_id' => $this->tenant_id,
            'compliance_record_id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'user_id' => auth()->id() ?? 1, // Fallback to system user
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
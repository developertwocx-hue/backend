<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'vehicle_type_id',
        'status',
        'qr_code_token',
        'state_of_operation',
        'operational_status',
        'compliance_score',
    ];

    protected $casts = [
        'compliance_score' => 'decimal:2',
    ];

    protected $appends = ['compliance_status'];

    /**
     * Boot function to auto-generate QR token and compliance requirements
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($vehicle) {
            if (empty($vehicle->qr_code_token)) {
                $vehicle->qr_code_token = \Illuminate\Support\Str::random(32);
            }
        });

        // Auto-generate compliance requirements when vehicle created
        static::created(function ($vehicle) {
            $vehicle->generateComplianceRequirements();
            $vehicle->updateComplianceScore();
        });

        // Update compliance score when vehicle updated
        static::updated(function ($vehicle) {
            if ($vehicle->isDirty('state_of_operation') || $vehicle->isDirty('vehicle_type_id')) {
                $vehicle->generateComplianceRequirements();
            }
            $vehicle->updateComplianceScore();
        });
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function stateOfOperation()
    {
        return $this->belongsTo(AustralianState::class, 'state_of_operation', 'code');
    }

    public function documents()
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function fieldValues()
    {
        return $this->hasMany(VehicleFieldValue::class);
    }

    // Compliance relationships
    public function complianceRequirements()
    {
        return $this->hasMany(VehicleComplianceRequirement::class);
    }

    public function complianceRecords()
    {
        return $this->hasMany(ComplianceRecord::class);
    }

    public function currentComplianceRecords()
    {
        return $this->hasMany(ComplianceRecord::class)->where('is_current', true);
    }

    public function complianceAlerts()
    {
        return $this->hasMany(ComplianceAlert::class);
    }

    // ============================================
    // COMPLIANCE STATUS ENGINE
    // ============================================

    /**
     * Get overall compliance status (computed, not stored)
     * Returns: 'compliant', 'at_risk', 'expired', 'pending'
     */
    public function getComplianceStatusAttribute()
    {
        $requirements = $this->complianceRequirements()
            ->where('is_required', true)
            ->with('currentRecord')
            ->get();

        if ($requirements->isEmpty()) {
            return 'compliant'; // No requirements = compliant
        }

        $statuses = $requirements->map(function($req) {
            return $req->getCurrentStatus();
        });

        // Worst-case rule: One expired = vehicle expired
        if ($statuses->contains('expired')) {
            return 'expired';
        }
        if ($statuses->contains('at_risk')) {
            return 'at_risk';
        }
        if ($statuses->contains('pending')) {
            return 'pending';
        }
        return 'compliant';
    }

    /**
     * Calculate compliance score (0-100) and cache it
     */
    public function calculateComplianceScore()
    {
        $requirements = $this->complianceRequirements()
            ->where('is_required', true)
            ->with('currentRecord')
            ->get();

        if ($requirements->isEmpty()) {
            return 100.00;
        }

        $totalWeight = $requirements->count();
        $score = 0;

        foreach ($requirements as $req) {
            $status = $req->getCurrentStatus();
            switch ($status) {
                case 'compliant':
                    $score += 100;
                    break;
                case 'at_risk':
                    $score += 50;
                    break;
                case 'expired':
                case 'pending':
                    $score += 0;
                    break;
            }
        }

        return round($score / $totalWeight, 2);
    }

    /**
     * Update cached compliance score
     */
    public function updateComplianceScore()
    {
        $newScore = $this->calculateComplianceScore();
        $this->withoutEvents(function () use ($newScore) {
            $this->update(['compliance_score' => $newScore]);
        });

        // Update operational status based on compliance
        $this->updateOperationalStatus();

        return $newScore;
    }

    /**
     * Update operational status based on compliance
     */
    public function updateOperationalStatus()
    {
        $complianceStatus = $this->getComplianceStatusAttribute();

        // Don't override maintenance status
        if ($this->operational_status === 'maintenance') {
            return;
        }

        $newStatus = match($complianceStatus) {
            'expired' => 'non_operational',
            'at_risk', 'pending', 'compliant' => 'operational',
            default => 'operational',
        };

        if ($this->operational_status !== $newStatus) {
            $this->withoutEvents(function () use ($newStatus) {
                $this->update(['operational_status' => $newStatus]);
            });
        }
    }

    // ============================================
    // AUTO-GENERATE COMPLIANCE REQUIREMENTS
    // ============================================

    /**
     * Auto-generate compliance requirements based on vehicle type and state
     */
    public function generateComplianceRequirements()
    {
        // Get all applicable compliance types for this vehicle
        $complianceTypes = ComplianceType::forVehicle($this)->get();

        foreach ($complianceTypes as $type) {
            VehicleComplianceRequirement::firstOrCreate([
                'vehicle_id' => $this->id,
                'compliance_type_id' => $type->id,
            ], [
                'tenant_id' => $this->tenant_id,
                'is_required' => $type->is_required,
            ]);
        }
    }

    /**
     * Refresh compliance requirements (useful after state/type changes)
     */
    public function refreshComplianceRequirements()
    {
        // Remove requirements that no longer apply
        $applicableTypeIds = ComplianceType::forVehicle($this)->pluck('id');

        $this->complianceRequirements()
            ->whereNotIn('compliance_type_id', $applicableTypeIds)
            ->delete();

        // Add new requirements
        $this->generateComplianceRequirements();

        // Recalculate score
        $this->updateComplianceScore();
    }

    // ============================================
    // COMPLIANCE HELPERS
    // ============================================

    /**
     * Check if vehicle is compliant
     */
    public function isCompliant()
    {
        return $this->compliance_status === 'compliant';
    }

    /**
     * Check if vehicle has expired compliance
     */
    public function hasExpiredCompliance()
    {
        return $this->compliance_status === 'expired';
    }

    /**
     * Check if vehicle can operate (compliance-wise)
     */
    public function canOperate()
    {
        return in_array($this->compliance_status, ['compliant', 'at_risk']);
    }

    /**
     * Get all expired compliance requirements
     */
    public function getExpiredCompliance()
    {
        return $this->complianceRequirements()
            ->with(['currentRecord', 'complianceType'])
            ->get()
            ->filter(function($req) {
                return $req->getCurrentStatus() === 'expired';
            });
    }

    /**
     * Get compliance expiring soon
     */
    public function getExpiringCompliance($days = 30)
    {
        return $this->complianceRequirements()
            ->with(['currentRecord', 'complianceType'])
            ->get()
            ->filter(function($req) use ($days) {
                $daysUntil = $req->getDaysUntilExpiry();
                return $daysUntil !== null && $daysUntil >= 0 && $daysUntil <= $days;
            });
    }

    /**
     * Get compliance summary for dashboard
     */
    public function getComplianceSummary()
    {
        $requirements = $this->complianceRequirements()
            ->where('is_required', true)
            ->with(['currentRecord', 'complianceType'])
            ->get();

        $summary = [
            'total_requirements' => $requirements->count(),
            'compliant' => 0,
            'at_risk' => 0,
            'expired' => 0,
            'pending' => 0,
            'compliance_score' => $this->compliance_score,
            'overall_status' => $this->compliance_status,
            'can_operate' => $this->canOperate(),
        ];

        foreach ($requirements as $req) {
            $status = $req->getCurrentStatus();
            $summary[$status]++;
        }

        return $summary;
    }

    /**
     * Get next expiring compliance
     */
    public function getNextExpiringCompliance()
    {
        return $this->complianceRequirements()
            ->with(['currentRecord', 'complianceType'])
            ->get()
            ->filter(function($req) {
                $days = $req->getDaysUntilExpiry();
                return $days !== null && $days >= 0;
            })
            ->sortBy(function($req) {
                return $req->getDaysUntilExpiry();
            })
            ->first();
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeCompliant($query)
    {
        return $query->where('compliance_score', '>=', 80);
    }

    public function scopeNonCompliant($query)
    {
        return $query->where('compliance_score', '<', 80);
    }

    public function scopeOperational($query)
    {
        return $query->where('operational_status', 'operational');
    }

    public function scopeNonOperational($query)
    {
        return $query->where('operational_status', 'non_operational');
    }

    public function scopeByState($query, $stateCode)
    {
        return $query->where('state_of_operation', $stateCode);
    }
}
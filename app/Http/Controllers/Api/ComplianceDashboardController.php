<?php

namespace App\Http\Controllers\Api;

use App\Models\Vehicle;
use App\Models\ComplianceRecord;
use App\Models\ComplianceAlert;
use App\Models\VehicleComplianceRequirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplianceDashboardController extends ApiController
{
    /**
     * Get overall fleet compliance statistics
     * GET /api/compliance/dashboard/stats
     */
    public function getStats(Request $request)
    {
        $user = $request->user();

        // Build base query with tenant filter
        $query = Vehicle::query();
        if ($user->role !== 'superadmin') {
            $query->where('tenant_id', $user->tenant_id);
        }

        $vehicles = $query->with(['complianceRequirements.complianceType', 'complianceRequirements.currentRecord'])->get();

        // Initialize counters
        $stats = [
            'fleet_overview' => [
                'total_vehicles' => $vehicles->count(),
                'operational' => 0,
                'non_operational' => 0,
                'maintenance' => 0,
            ],
            'compliance_overview' => [
                'compliant' => 0,
                'at_risk' => 0,
                'expired' => 0,
                'pending' => 0,
            ],
            'compliance_rate' => [
                'percentage' => 0,
                'compliant_vehicles' => 0,
                'total_vehicles' => $vehicles->count(),
            ],
            'requirements_overview' => [
                'total_requirements' => 0,
                'compliant' => 0,
                'at_risk' => 0,
                'expired' => 0,
                'pending' => 0,
            ],
            'expiring_soon' => [
                'within_7_days' => 0,
                'within_14_days' => 0,
                'within_30_days' => 0,
            ],
            'average_compliance_score' => 0,
        ];

        // Process each vehicle
        $totalScore = 0;
        $vehiclesWithScore = 0;

        foreach ($vehicles as $vehicle) {
            // Operational status counts
            switch ($vehicle->operational_status) {
                case 'operational':
                    $stats['fleet_overview']['operational']++;
                    break;
                case 'non_operational':
                    $stats['fleet_overview']['non_operational']++;
                    break;
                case 'maintenance':
                    $stats['fleet_overview']['maintenance']++;
                    break;
            }

            // Compliance status counts
            $complianceStatus = $vehicle->compliance_status;
            
            if (isset($stats['compliance_overview'][$complianceStatus])) {
                $stats['compliance_overview'][$complianceStatus]++;
            }

            // Also count pending as at_risk
            if ($complianceStatus === 'pending') {
                $stats['compliance_overview']['at_risk']++;
            }

            // Compliance rate (only count compliant vehicles)
            if ($complianceStatus === 'compliant') {
                $stats['compliance_rate']['compliant_vehicles']++;
            }

            // Average score calculation
            if ($vehicle->compliance_score !== null) {
                $totalScore += (float)$vehicle->compliance_score;
                $vehiclesWithScore++;
            }

            // Requirements analysis
            $requirements = $vehicle->complianceRequirements;
            $stats['requirements_overview']['total_requirements'] += $requirements->count();

            foreach ($requirements as $requirement) {
                $status = $requirement->getCurrentStatus();

                if (isset($stats['requirements_overview'][$status])) {
                    $stats['requirements_overview'][$status]++;
                }

                // Also count pending as at_risk
                if ($status === 'pending') {
                    $stats['requirements_overview']['at_risk']++;
                }

                // Check expiring soon
                $daysUntil = $requirement->getDaysUntilExpiry();
                if ($daysUntil !== null && $daysUntil >= 0) {
                    if ($daysUntil <= 7) {
                        $stats['expiring_soon']['within_7_days']++;
                    }
                    if ($daysUntil <= 14) {
                        $stats['expiring_soon']['within_14_days']++;
                    }
                    if ($daysUntil <= 30) {
                        $stats['expiring_soon']['within_30_days']++;
                    }
                }
            }
        }

        // Calculate averages and percentages
        $stats['compliance_rate']['percentage'] = $vehicles->count() > 0
            ? round(($stats['compliance_rate']['compliant_vehicles'] / $vehicles->count()) * 100, 2)
            : 0;

        $stats['average_compliance_score'] = $vehiclesWithScore > 0
            ? round($totalScore / $vehiclesWithScore, 2)
            : 0;

        return $this->successResponse($stats, 'Fleet compliance statistics retrieved successfully');
    }

    /**
     * Get fleet at risk - vehicles with at_risk, expired, or pending compliance
     * GET /api/compliance/dashboard/fleet-at-risk?page=1&limit=20
     */
    public function getFleetAtRisk(Request $request)
    {
        $user = $request->user();
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 20);

        // Build base query
        $query = Vehicle::query();
        if ($user->role !== 'superadmin') {
            $query->where('tenant_id', $user->tenant_id);
        }

        // Get all vehicles to filter by compliance status
        $vehicles = $query->with([
            'vehicleType',
            'complianceRequirements.complianceType',
            'complianceRequirements.currentRecord'
        ])->get();

        // Filter vehicles that are at risk, expired, or have pending compliance
        $atRiskVehicles = $vehicles->filter(function ($vehicle) {
            $status = $vehicle->compliance_status;
            return in_array($status, ['at_risk', 'expired', 'pending']);
        });

        // Map to detailed format
        $fleetAtRisk = $atRiskVehicles->map(function ($vehicle) {
            $requirements = $vehicle->complianceRequirements;

            // Get problematic requirements (at_risk, expired, or pending)
            $problematicRequirements = $requirements->filter(function ($req) {
                $status = $req->getCurrentStatus();
                return in_array($status, ['at_risk', 'expired', 'pending']);
            })->map(function ($req) {
                return [
                    'requirement_id' => $req->id,
                    'compliance_type' => $req->complianceType->name ?? 'Unknown',
                    'category' => $req->complianceType->category ?? 'Unknown',
                    'status' => $req->getCurrentStatus(),
                    'days_until_expiry' => $req->getDaysUntilExpiry() ?? '0',
                    'expiry_date' => $req->currentRecord?->expiry_date?->format('Y-m-d'),
                    'is_required' => $req->is_required,
                ];
            })->values();

            return [
                'vehicle_id' => $vehicle->id,
                'vehicle_type' => $vehicle->vehicleType->name ?? 'Unknown',
                'state_of_operation' => $vehicle->state_of_operation,
                'compliance_status' => $vehicle->compliance_status,
                'compliance_score' => $vehicle->compliance_score,
                'operational_status' => $vehicle->operational_status,
                'problematic_requirements' => $problematicRequirements,
                'problem_count' => $problematicRequirements->count(),
            ];
        })->sortByDesc('problem_count')->values();

        // Get total count before pagination
        $total = $fleetAtRisk->count();

        // Apply pagination
        $offset = ($page - 1) * $limit;
        $paginatedVehicles = $fleetAtRisk->slice($offset, $limit)->values();

        return $this->successResponse([
            'total_at_risk' => $total,
            'current_page' => (int) $page,
            'per_page' => (int) $limit,
            'total_pages' => ceil($total / $limit),
            'has_more' => $page < ceil($total / $limit),
            'vehicles' => $paginatedVehicles,
        ], 'Fleet at risk retrieved successfully');
    }

    /**
     * Get overdue items - all expired compliance records
     * GET /api/compliance/dashboard/overdue-items
     */
    public function getOverdueItems(Request $request)
    {
        $user = $request->user();

        // Build base query
        $query = Vehicle::query();
        if ($user->role !== 'superadmin') {
            $query->where('tenant_id', $user->tenant_id);
        }

        $vehicles = $query->with([
            'vehicleType',
            'complianceRequirements.complianceType',
            'complianceRequirements.currentRecord'
        ])->get();

        // Collect all overdue items
        $overdueItems = [];

        foreach ($vehicles as $vehicle) {
            $requirements = $vehicle->complianceRequirements;

            foreach ($requirements as $requirement) {
                if ($requirement->isOverdue()) {
                    $overdueItems[] = [
                        'vehicle_id' => $vehicle->id,
                        'vehicle_type' => $vehicle->vehicleType->name ?? 'Unknown',
                        'state_of_operation' => $vehicle->state_of_operation,
                        'requirement_id' => $requirement->id,
                        'compliance_type_id' => $requirement->compliance_type_id,
                        'compliance_type_name' => $requirement->complianceType->name ?? 'Unknown',
                        'category' => $requirement->complianceType->category ?? 'Unknown',
                        'is_required' => $requirement->is_required,
                        'expiry_date' => $requirement->currentRecord?->expiry_date?->format('Y-m-d'),
                        'days_overdue' => abs($requirement->getDaysUntilExpiry()),
                        'current_record_id' => $requirement->currentRecord?->id,
                    ];
                }
            }
        }

        // Sort by days overdue (most overdue first)
        $overdueItems = collect($overdueItems)->sortByDesc('days_overdue')->values();

        return $this->successResponse([
            'total_overdue' => $overdueItems->count(),
            'items' => $overdueItems,
        ], 'Overdue items retrieved successfully');
    }

    /**
     * Get active alerts for the tenant
     * GET /api/compliance/dashboard/alerts
     */
    public function getAlerts(Request $request)
    {
        $user = $request->user();

        // Build base query
        $query = ComplianceAlert::with([
            'vehicle.vehicleType',
            'complianceRecord.complianceType',
            'acknowledgedBy'
        ]);

        if ($user->role !== 'superadmin') {
            $query->where('tenant_id', $user->tenant_id);
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            // Default: show unacknowledged alerts
            $query->unacknowledged();
        }

        // Filter by alert type if provided
        if ($request->has('alert_type')) {
            $query->where('alert_type', $request->alert_type);
        }

        $alerts = $query->latest()->get();

        // Map to detailed format
        $alertsData = $alerts->map(function ($alert) {
            return [
                'alert_id' => $alert->id,
                'alert_type' => $alert->alert_type,
                'status' => $alert->status,
                'days_until_expiry' => $alert->days_until_expiry,
                'vehicle' => [
                    'id' => $alert->vehicle->id,
                    'type' => $alert->vehicle->vehicleType->name ?? 'Unknown',
                    'state' => $alert->vehicle->state_of_operation,
                ],
                'compliance_type' => $alert->complianceRecord?->complianceType?->name ?? 'Unknown',
                'category' => $alert->complianceRecord?->complianceType?->category ?? 'unknown',
                'expiry_date' => $alert->complianceRecord?->expiry_date?->format('Y-m-d'),
                'sent_at' => $alert->sent_at?->format('Y-m-d H:i:s'),
                'acknowledged_at' => $alert->acknowledged_at?->format('Y-m-d H:i:s'),
                'acknowledged_by' => $alert->acknowledgedBy?->name,
                'created_at' => $alert->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->successResponse([
            'total_alerts' => $alertsData->count(),
            'alerts' => $alertsData,
        ], 'Alerts retrieved successfully');
    }

    /**
     * Get expiring soon - items expiring within specified days
     * GET /api/compliance/dashboard/expiring-soon?days=30
     */
    public function getExpiringSoon(Request $request)
    {
        $user = $request->user();
        $days = $request->input('days', 30); // Default 30 days

        // Build base query
        $query = Vehicle::query();
        if ($user->role !== 'superadmin') {
            $query->where('tenant_id', $user->tenant_id);
        }

        $vehicles = $query->with([
            'vehicleType',
            'complianceRequirements.complianceType',
            'complianceRequirements.currentRecord'
        ])->get();

        // Collect items expiring within the specified days
        $expiringSoon = [];

        foreach ($vehicles as $vehicle) {
            $requirements = $vehicle->complianceRequirements;

            foreach ($requirements as $requirement) {
                $daysUntil = $requirement->getDaysUntilExpiry();

                if ($daysUntil !== null && $daysUntil >= 0 && $daysUntil <= $days) {
                    $expiringSoon[] = [
                        'vehicle_id' => $vehicle->id,
                        'vehicle_type' => $vehicle->vehicleType->name ?? 'Unknown',
                        'state_of_operation' => $vehicle->state_of_operation,
                        'requirement_id' => $requirement->id,
                        'compliance_type_id' => $requirement->compliance_type_id,
                        'compliance_type_name' => $requirement->complianceType->name ?? 'Unknown',
                        'category' => $requirement->complianceType->category ?? 'Unknown',
                        'is_required' => $requirement->is_required,
                        'status' => $requirement->getCurrentStatus(),
                        'expiry_date' => $requirement->currentRecord?->expiry_date?->format('Y-m-d'),
                        'days_until_expiry' => $daysUntil,
                        'current_record_id' => $requirement->currentRecord?->id,
                    ];
                }
            }
        }

        // Sort by days until expiry (soonest first)
        $expiringSoon = collect($expiringSoon)->sortBy('days_until_expiry')->values();

        return $this->successResponse([
            'days_threshold' => $days,
            'total_expiring_soon' => $expiringSoon->count(),
            'items' => $expiringSoon,
        ], "Items expiring within {$days} days retrieved successfully");
    }

    /**
     * Acknowledge an alert
     * POST /api/compliance/dashboard/alerts/{alertId}/acknowledge
     */
    public function acknowledgeAlert(Request $request, $alertId)
    {
        $user = $request->user();

        $query = ComplianceAlert::query();
        if ($user->role !== 'superadmin') {
            $query->where('tenant_id', $user->tenant_id);
        }

        $alert = $query->findOrFail($alertId);

        if ($alert->acknowledged_at) {
            return $this->errorResponse('Alert already acknowledged', 400);
        }

        $alert->acknowledge($user->id);

        return $this->successResponse($alert, 'Alert acknowledged successfully');
    }

    /**
     * Get compliance summary by category
     * GET /api/compliance/dashboard/summary-by-category
     */
    public function getSummaryByCategory(Request $request)
    {
        $user = $request->user();

        // Build base query
        $query = Vehicle::query();
        if ($user->role !== 'superadmin') {
            $query->where('tenant_id', $user->tenant_id);
        }

        $vehicles = $query->with([
            'complianceRequirements.complianceType',
            'complianceRequirements.currentRecord'
        ])->get();

        // Initialize category summary
        $categorySummary = [];

        foreach ($vehicles as $vehicle) {
            $requirements = $vehicle->complianceRequirements;

            foreach ($requirements as $requirement) {
                $category = $requirement->complianceType->category ?? 'Unknown';
                $status = $requirement->getCurrentStatus();

                if (!isset($categorySummary[$category])) {
                    $categorySummary[$category] = [
                        'category' => $category,
                        'total' => 0,
                        'compliant' => 0,
                        'at_risk' => 0,
                        'expired' => 0,
                        'pending' => 0,
                    ];
                }

                $categorySummary[$category]['total']++;
                if (isset($categorySummary[$category][$status])) {
                    $categorySummary[$category][$status]++;
                }

                // Also count pending as at_risk
                if ($status === 'pending') {
                    $categorySummary[$category]['at_risk']++;
                }
            }
        }

        // Convert to array and calculate percentages
        $categorySummary = collect($categorySummary)->map(function ($cat) {
            $cat['compliance_rate'] = $cat['total'] > 0
                ? round(($cat['compliant'] / $cat['total']) * 100, 2)
                : 0;
            return $cat;
        })->values();

        return $this->successResponse([
            'categories' => $categorySummary,
        ], 'Compliance summary by category retrieved successfully');
    }
}

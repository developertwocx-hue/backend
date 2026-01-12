<?php

namespace App\Jobs;

use App\Models\ComplianceAlert;
use App\Models\ComplianceRecord;
use App\Models\ComplianceType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateComplianceAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting compliance alert generation...');

        // Get all current compliance records with their compliance types
        $records = ComplianceRecord::with(['complianceType', 'vehicle'])
            ->where('is_current', true)
            ->whereNotNull('expiry_date')
            ->get();

        $alertsCreated = 0;
        $alertsSkipped = 0;

        foreach ($records as $record) {
            if (!$record->complianceType) {
                continue;
            }

            $daysUntilExpiry = $record->days_until_expiry;
            $alertThresholds = $record->complianceType->alert_thresholds ?? [];

            // Skip if no alert thresholds defined
            if (empty($alertThresholds)) {
                continue;
            }

            // Determine which alert type should be triggered
            $alertType = $this->determineAlertType($daysUntilExpiry, $alertThresholds);

            if (!$alertType) {
                $alertsSkipped++;
                continue;
            }

            // Check if alert already exists for this record and type
            $existingAlert = ComplianceAlert::where('compliance_record_id', $record->id)
                ->where('alert_type', $alertType)
                ->where('status', '!=', 'acknowledged')
                ->first();

            if ($existingAlert) {
                $alertsSkipped++;
                continue;
            }

            // Create the alert
            try {
                $alert = ComplianceAlert::create([
                    'tenant_id' => $record->vehicle->tenant_id,
                    'compliance_record_id' => $record->id,
                    'vehicle_id' => $record->vehicle_id,
                    'alert_type' => $alertType,
                    'days_until_expiry' => $daysUntilExpiry,
                    'status' => 'pending',
                    'sent_to' => $this->getAlertRecipients($record),
                ]);

                $alertsCreated++;
                Log::info("Created {$alertType} alert for compliance record {$record->id}");
            } catch (\Exception $e) {
                Log::error("Failed to create alert for compliance record {$record->id}: {$e->getMessage()}");
            }
        }

        Log::info("Compliance alert generation completed. Created: {$alertsCreated}, Skipped: {$alertsSkipped}");
    }

    /**
     * Determine which alert type should be triggered based on days until expiry
     */
    private function determineAlertType(int $daysUntilExpiry, array $thresholds): ?string
    {
        // Sort thresholds in descending order
        rsort($thresholds);

        // Handle expired case
        if ($daysUntilExpiry < 0) {
            return 'expired';
        }

        // Check each threshold
        foreach ($thresholds as $threshold) {
            if ($threshold == 0 && $daysUntilExpiry == 0) {
                return 'expired';
            } elseif ($threshold == 7 && $daysUntilExpiry <= 7 && $daysUntilExpiry > 0) {
                return 'expiring_7';
            } elseif ($threshold == 14 && $daysUntilExpiry <= 14 && $daysUntilExpiry > 7) {
                return 'expiring_14';
            } elseif ($threshold == 30 && $daysUntilExpiry <= 30 && $daysUntilExpiry > 14) {
                return 'expiring_30';
            }
        }

        return null;
    }

    /**
     * Get recipients for alerts (can be customized based on tenant settings)
     */
    private function getAlertRecipients(ComplianceRecord $record): array
    {
        $recipients = [];

        // Add vehicle owner/manager if exists
        if ($record->vehicle && $record->vehicle->tenant) {
            // Get tenant admin users
            $adminUsers = $record->vehicle->tenant->users()
                ->whereIn('role', ['admin', 'manager'])
                ->get();

            foreach ($adminUsers as $user) {
                $recipients[] = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ];
            }
        }

        return $recipients;
    }
}
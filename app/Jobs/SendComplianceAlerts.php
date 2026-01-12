<?php

namespace App\Jobs;

use App\Models\ComplianceAlert;
use App\Models\User;
use App\Notifications\ComplianceAlertNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendComplianceAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting to send compliance alerts...');

        // Get all pending alerts
        $pendingAlerts = ComplianceAlert::with(['complianceRecord.complianceType', 'vehicle'])
            ->where('status', 'pending')
            ->get();

        $alertsSent = 0;
        $alertsFailed = 0;

        foreach ($pendingAlerts as $alert) {
            try {
                $recipients = $this->getRecipients($alert);

                if (empty($recipients)) {
                    Log::warning("No recipients found for alert {$alert->id}");
                    continue;
                }

                // Send notification to each recipient
                foreach ($recipients as $recipientData) {
                    $user = User::find($recipientData['user_id']);

                    if ($user) {
                        $user->notify(new ComplianceAlertNotification($alert));
                    }
                }

                // Update alert status
                $alert->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                $alertsSent++;
                Log::info("Successfully sent alert {$alert->id} to " . count($recipients) . " recipients");

            } catch (\Exception $e) {
                $alert->update(['status' => 'failed']);
                $alertsFailed++;
                Log::error("Failed to send alert {$alert->id}: {$e->getMessage()}");
            }
        }

        Log::info("Compliance alerts sent. Success: {$alertsSent}, Failed: {$alertsFailed}");
    }

    /**
     * Get recipients for the alert
     */
    private function getRecipients(ComplianceAlert $alert): array
    {
        // If sent_to is already populated, use it
        if (!empty($alert->sent_to)) {
            return $alert->sent_to;
        }

        // Otherwise, get tenant admins/managers
        $recipients = [];

        if ($alert->vehicle && $alert->vehicle->tenant) {
            $adminUsers = $alert->vehicle->tenant->users()
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
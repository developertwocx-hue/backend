<?php

namespace App\Console\Commands;

use App\Jobs\SendComplianceAlerts;
use App\Models\ComplianceAlert;
use Illuminate\Console\Command;

class SendComplianceAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:send-alerts
                            {--alert-id= : Send a specific alert by ID}
                            {--type= : Send alerts of a specific type (expiring_30, expiring_14, expiring_7, expired)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send pending compliance alert notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting to send compliance alerts...');

        try {
            // Check for specific alert ID
            if ($alertId = $this->option('alert-id')) {
                return $this->sendSpecificAlert($alertId);
            }

            // Check for specific alert type
            if ($type = $this->option('type')) {
                return $this->sendAlertsByType($type);
            }

            // Send all pending alerts
            $pendingCount = ComplianceAlert::where('status', 'pending')->count();

            if ($pendingCount === 0) {
                $this->info('No pending alerts to send.');
                return self::SUCCESS;
            }

            $this->info("Found {$pendingCount} pending alerts.");

            $job = new SendComplianceAlerts();
            $job->handle();

            $sentCount = ComplianceAlert::where('status', 'sent')
                ->whereDate('sent_at', today())
                ->count();

            $this->info("✓ Successfully sent {$sentCount} alerts!");

            // Show any failed alerts
            $failedCount = ComplianceAlert::where('status', 'failed')
                ->whereDate('updated_at', today())
                ->count();

            if ($failedCount > 0) {
                $this->warn("⚠ {$failedCount} alerts failed to send.");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to send alerts: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Send a specific alert by ID
     */
    private function sendSpecificAlert(int $alertId): int
    {
        $alert = ComplianceAlert::find($alertId);

        if (!$alert) {
            $this->error("Alert with ID {$alertId} not found.");
            return self::FAILURE;
        }

        if ($alert->status === 'sent') {
            $this->warn("Alert {$alertId} has already been sent at {$alert->sent_at}.");

            if (!$this->confirm('Do you want to resend it?')) {
                return self::SUCCESS;
            }

            $alert->update(['status' => 'pending']);
        }

        $this->info("Sending alert {$alertId}...");

        $job = new SendComplianceAlerts();
        $job->handle();

        $alert->refresh();

        if ($alert->status === 'sent') {
            $this->info("✓ Alert {$alertId} sent successfully!");
            return self::SUCCESS;
        }

        $this->error("Failed to send alert {$alertId}.");
        return self::FAILURE;
    }

    /**
     * Send alerts of a specific type
     */
    private function sendAlertsByType(string $type): int
    {
        $validTypes = ['expiring_30', 'expiring_14', 'expiring_7', 'expired', 'escalation'];

        if (!in_array($type, $validTypes)) {
            $this->error("Invalid alert type: {$type}");
            $this->info("Valid types: " . implode(', ', $validTypes));
            return self::FAILURE;
        }

        $count = ComplianceAlert::where('status', 'pending')
            ->where('alert_type', $type)
            ->count();

        if ($count === 0) {
            $this->info("No pending {$type} alerts to send.");
            return self::SUCCESS;
        }

        $this->info("Found {$count} pending {$type} alerts.");

        $job = new SendComplianceAlerts();
        $job->handle();

        $sent = ComplianceAlert::where('status', 'sent')
            ->where('alert_type', $type)
            ->whereDate('sent_at', today())
            ->count();

        $this->info("✓ Sent {$sent} {$type} alerts!");

        return self::SUCCESS;
    }
}
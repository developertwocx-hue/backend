<?php

namespace App\Console\Commands;

use App\Jobs\GenerateComplianceAlerts;
use App\Jobs\SendComplianceAlerts;
use Illuminate\Console\Command;

class GenerateComplianceAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:generate-alerts
                            {--send : Immediately send generated alerts}
                            {--force : Force regenerate all alerts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate compliance alerts for vehicles with expiring or expired compliance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting compliance alert generation...');

        if ($this->option('force')) {
            $this->warn('Force mode: This will regenerate all applicable alerts.');

            if (!$this->confirm('Are you sure you want to continue?')) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }

            // Clear existing pending alerts if force mode
            $deleted = \App\Models\ComplianceAlert::where('status', 'pending')->delete();
            $this->info("Cleared {$deleted} pending alerts.");
        }

        // Dispatch the job synchronously so we can see the output
        try {
            $job = new GenerateComplianceAlerts();
            $job->handle();

            $this->info('✓ Compliance alerts generated successfully!');

            // Count generated alerts
            $pendingCount = \App\Models\ComplianceAlert::where('status', 'pending')->count();
            $this->info("Total pending alerts: {$pendingCount}");

            // Send alerts if requested
            if ($this->option('send')) {
                $this->info('Sending alerts...');
                $sendJob = new SendComplianceAlerts();
                $sendJob->handle();

                $sentCount = \App\Models\ComplianceAlert::where('status', 'sent')
                    ->whereDate('sent_at', today())
                    ->count();

                $this->info("✓ {$sentCount} alerts sent successfully!");
            } else {
                $this->info('Use --send flag to immediately send the generated alerts.');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to generate alerts: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
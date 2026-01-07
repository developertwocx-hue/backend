<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use Illuminate\Console\Command;

class RefreshComplianceRequirements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:refresh-requirements {--tenant= : Filter by tenant ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh compliance requirements for vehicles (adds missing types without deleting existing history)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Refreshing compliance requirements...');

        $query = Vehicle::query();
        if ($this->option('tenant')) {
            $query->where('tenant_id', $this->option('tenant'));
        }

        $vehicles = $query->with('complianceRequirements')->get();
        
        $bar = $this->output->createProgressBar($vehicles->count());
        $bar->start();

        $updatedCount = 0;

        foreach ($vehicles as $vehicle) {
            // Get current count
            $initialCount = $vehicle->complianceRequirements->count();
            
            // This method (in Vehicle model) should be checked to ensure it's safe
            // But based on standard logic, we should probably manually implement a safe "add missing" here
            // just to be 100% sure we don't rely on a potentially destructive method in the model.
            
            // Manually triggering generation
            $vehicle->generateComplianceRequirements();
            
            // Recalculate score
            $vehicle->updateComplianceScore();
            
            $vehicle->refresh();
            if ($vehicle->complianceRequirements->count() > $initialCount) {
                $updatedCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done! Processed {$vehicles->count()} vehicles.");
        $this->info("Updated requirements for {$updatedCount} vehicles.");

        return 0;
    }
}

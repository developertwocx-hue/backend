<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use Illuminate\Console\Command;

class RegenerateVehicleRequirements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vehicles:regenerate-requirements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate compliance requirements for all vehicles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Regenerating compliance requirements for all vehicles...');

        $vehicles = Vehicle::all();
        $bar = $this->output->createProgressBar($vehicles->count());
        $bar->start();

        foreach ($vehicles as $vehicle) {
            // Delete existing requirements
            $vehicle->complianceRequirements()->delete();

            // Regenerate based on current vehicle type (ignoring state)
            $vehicle->generateComplianceRequirements();

            // Recalculate score
            $vehicle->updateComplianceScore();

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done! Regenerated requirements for ' . $vehicles->count() . ' vehicles.');

        return 0;
    }
}

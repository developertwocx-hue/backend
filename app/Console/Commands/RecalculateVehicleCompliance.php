<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use Illuminate\Console\Command;

class RecalculateVehicleCompliance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vehicles:recalculate-compliance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate compliance scores for all vehicles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Recalculating compliance scores for all vehicles...');

        $vehicles = Vehicle::all();
        $bar = $this->output->createProgressBar($vehicles->count());
        $bar->start();

        foreach ($vehicles as $vehicle) {
            $vehicle->updateComplianceScore();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done! Recalculated ' . $vehicles->count() . ' vehicles.');

        return 0;
    }
}

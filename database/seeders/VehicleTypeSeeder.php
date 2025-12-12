<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VehicleType;

class VehicleTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicleTypes = [
            [
                'name' => 'Crane',
                'description' => 'Mobile cranes available to hire that are capable of handling all sizes of projects in Melbourne and Victoria.',
                'is_active' => true,
            ],
            [
                'name' => 'Light Vehicle',
                'description' => 'Light vehicles for transportation and support operations.',
                'is_active' => true,
            ],
            [
                'name' => 'Truck',
                'description' => 'Heavy-duty trucks for hauling and transportation.',
                'is_active' => true,
            ],
            [
                'name' => 'Van',
                'description' => 'Vans for light transport and logistics.',
                'is_active' => true,
            ],
        ];
        
        foreach ($vehicleTypes as $type) {
            VehicleType::create($type);
        }

        $this->command->info('✓ Vehicle types seeded successfully!');
    }
}

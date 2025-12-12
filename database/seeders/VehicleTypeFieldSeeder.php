<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VehicleType;
use App\Models\VehicleTypeField;

class VehicleTypeFieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $crane = VehicleType::where('name', 'Crane')->first();
        $lightVehicle = VehicleType::where('name', 'Light Vehicle')->first();
        $truck = VehicleType::where('name', 'Truck')->first();
        $van = VehicleType::where('name', 'Van')->first();

        // Common field for all vehicles
        $allVehicleTypes = [$crane, $lightVehicle, $truck, $van];

        foreach ($allVehicleTypes as $vehicleType) {
            VehicleTypeField::create([
                'vehicle_type_id' => $vehicleType->id,
                'tenant_id' => null, // Default field
                'name' => 'Name',
                'key' => 'name',
                'field_type' => 'text',
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 1,
                'description' => 'Vehicle identification name',
            ]);
        }

        // Crane-specific fields
        if ($crane) {
            $craneFields = [
                [
                    'name' => 'Lifting Capacity',
                    'key' => 'lifting_capacity',
                    'field_type' => 'text',
                    'unit' => 'TONNE',
                    'is_required' => true,
                    'sort_order' => 2,
                    'description' => 'Maximum lifting capacity',
                ],
                [
                    'name' => 'Boom Length',
                    'key' => 'boom_length',
                    'field_type' => 'text',
                    'unit' => 'M',
                    'is_required' => false,
                    'sort_order' => 3,
                    'description' => 'Boom length in meters',
                ],
                [
                    'name' => 'Telescopic Boom',
                    'key' => 'telescopic_boom',
                    'field_type' => 'text',
                    'unit' => 'M',
                    'is_required' => false,
                    'sort_order' => 4,
                    'description' => 'Telescopic boom extension',
                ],
                [
                    'name' => 'Make',
                    'key' => 'make',
                    'field_type' => 'text',
                    'is_required' => false,
                    'sort_order' => 5,
                    'description' => 'Manufacturer',
                ],
                [
                    'name' => 'Model',
                    'key' => 'model',
                    'field_type' => 'text',
                    'is_required' => false,
                    'sort_order' => 6,
                    'description' => 'Model number/name',
                ],
            ];

            foreach ($craneFields as $field) {
                VehicleTypeField::create(array_merge($field, [
                    'vehicle_type_id' => $crane->id,
                    'tenant_id' => null,
                    'is_active' => true,
                ]));
            }
        }

        // Light Vehicle, Truck, and Van common fields
        $commonVehicleTypes = [$lightVehicle, $truck, $van];

        foreach ($commonVehicleTypes as $vehicleType) {
            if ($vehicleType) {
                $commonFields = [
                    [
                        'name' => 'Make',
                        'key' => 'make',
                        'field_type' => 'text',
                        'is_required' => false,
                        'sort_order' => 2,
                        'description' => 'Vehicle manufacturer',
                    ],
                    [
                        'name' => 'Model',
                        'key' => 'model',
                        'field_type' => 'text',
                        'is_required' => false,
                        'sort_order' => 3,
                        'description' => 'Vehicle model',
                    ],
                    [
                        'name' => 'Year',
                        'key' => 'year',
                        'field_type' => 'number',
                        'is_required' => false,
                        'sort_order' => 4,
                        'description' => 'Manufacturing year',
                    ],
                    [
                        'name' => 'Registration',
                        'key' => 'registration',
                        'field_type' => 'text',
                        'is_required' => false,
                        'sort_order' => 5,
                        'description' => 'Registration number',
                    ],
                ];

                foreach ($commonFields as $field) {
                    VehicleTypeField::create(array_merge($field, [
                        'vehicle_type_id' => $vehicleType->id,
                        'tenant_id' => null,
                        'is_active' => true,
                    ]));
                }
            }
        }

        $this->command->info('✓ Vehicle type fields seeded successfully!');
    }
}

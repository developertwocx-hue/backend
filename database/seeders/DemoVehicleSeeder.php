<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VehicleType;
use App\Models\Vehicle;
use App\Models\VehicleTypeField;
use App\Models\VehicleFieldValue;

class DemoVehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Note: This seeder requires a tenant context to run.
     * Run it within a tenant using: php artisan tenants:seed --tenants=<tenant-id>
     */
    public function run(): void
    {
        $crane = VehicleType::where('name', 'Crane')->first();
        $lightVehicle = VehicleType::where('name', 'Light Vehicle')->first();
        $truck = VehicleType::where('name', 'Truck')->first();
        $van = VehicleType::where('name', 'Van')->first();

        // Crane vehicles
        if ($crane) {
            $cranes = [
                ['name' => 'AWD-05 FRANNA MAC / 25 Tonne', 'make' => 'FRANNA', 'model' => 'MAC', 'capacity' => '25', 'boom_length' => '18', 'telescopic_boom' => '4'],
                ['name' => 'AT-07 ZOOMLION ZAT1200V753 / 120 Tonne', 'make' => 'ZOOMLION', 'model' => 'ZAT1200V753', 'capacity' => '120', 'boom_length' => null, 'telescopic_boom' => null],
                ['name' => 'UV-04 HUMMA UV35-25 / 35 Tonne', 'make' => 'HUMMA', 'model' => 'UV35-25', 'capacity' => '35', 'boom_length' => null, 'telescopic_boom' => null],
                ['name' => 'AT-05 LIEBHERR LTM 1070-4.1 / 70 Tonne', 'make' => 'LIEBHERR', 'model' => 'LTM 1070-4.1', 'capacity' => '70', 'boom_length' => null, 'telescopic_boom' => null],
                ['name' => 'AWD-02 FRANNA AT 20 / 20 Tonne', 'make' => 'FRANNA', 'model' => 'AT 20', 'capacity' => '20', 'boom_length' => null, 'telescopic_boom' => null],
                ['name' => 'UV-03 HUMMA UV35-25 / 35 Tonne', 'make' => 'HUMMA', 'model' => 'UV35-25', 'capacity' => '35', 'boom_length' => null, 'telescopic_boom' => null],
                ['name' => 'UV-02 HUMMA UV35-25 / 35 Tonne', 'make' => 'HUMMA', 'model' => 'UV35-25', 'capacity' => '35', 'boom_length' => null, 'telescopic_boom' => null],
                ['name' => 'CC-01 KOBELCO RK160-2 / 16 Tonne', 'make' => 'KOBELCO', 'model' => 'RK160-2', 'capacity' => '16', 'boom_length' => null, 'telescopic_boom' => null],
                ['name' => 'AT-04 LIEBHERR LTM 1055-3.2 / 55 Tonne', 'make' => 'LIEBHERR', 'model' => 'LTM 1055-3.2', 'capacity' => '55', 'boom_length' => null, 'telescopic_boom' => null],
                ['name' => 'AT-03 GROVE GMK 5220 / 220 Tonne', 'make' => 'GROVE', 'model' => 'GMK 5220', 'capacity' => '220', 'boom_length' => null, 'telescopic_boom' => null],
                ['name' => 'AT-02 LIEBHERR LTM 1070-4.1 / 70 Tonne', 'make' => 'LIEBHERR', 'model' => 'LTM 1070-4.1', 'capacity' => '70', 'boom_length' => null, 'telescopic_boom' => null],
                ['name' => 'UV-01 HUMMA UV35-25 / 35 Tonne', 'make' => 'HUMMA', 'model' => 'UV35-25', 'capacity' => '35', 'boom_length' => null, 'telescopic_boom' => null],
                ['name' => 'AWD-03 MAC / 25 Tonne', 'make' => 'MAC', 'model' => null, 'capacity' => '25', 'boom_length' => null, 'telescopic_boom' => null],
            ];

            foreach ($cranes as $craneData) {
                $vehicle = Vehicle::create([
                    'vehicle_type_id' => $crane->id,
                    'status' => 'active',
                ]);

                $this->createFieldValue($vehicle, $crane, 'name', $craneData['name']);
                $this->createFieldValue($vehicle, $crane, 'make', $craneData['make']);
                if ($craneData['model']) {
                    $this->createFieldValue($vehicle, $crane, 'model', $craneData['model']);
                }
                $this->createFieldValue($vehicle, $crane, 'lifting_capacity', $craneData['capacity']);
                if ($craneData['boom_length']) {
                    $this->createFieldValue($vehicle, $crane, 'boom_length', $craneData['boom_length']);
                }
                if ($craneData['telescopic_boom']) {
                    $this->createFieldValue($vehicle, $crane, 'telescopic_boom', $craneData['telescopic_boom']);
                }
            }

            $this->command->info('✓ Crane vehicles seeded!');
        }

        // Light vehicles
        if ($lightVehicle) {
            $lightVehicles = [
                ['name' => 'LV-02 GREAT WALL STEED', 'make' => 'GREAT WALL', 'model' => 'STEED'],
                ['name' => 'LV-01 TOYOTA HILUX', 'make' => 'TOYOTA', 'model' => 'HILUX'],
            ];

            foreach ($lightVehicles as $lvData) {
                $vehicle = Vehicle::create([
                    'vehicle_type_id' => $lightVehicle->id,
                    'status' => 'active',
                ]);

                $this->createFieldValue($vehicle, $lightVehicle, 'name', $lvData['name']);
                $this->createFieldValue($vehicle, $lightVehicle, 'make', $lvData['make']);
                $this->createFieldValue($vehicle, $lightVehicle, 'model', $lvData['model']);
            }

            $this->command->info('✓ Light vehicles seeded!');
        }

        // Trucks
        if ($truck) {
            $trucks = [
                ['name' => 'LV-03 ISUZU NLR45 Truck', 'make' => 'ISUZU', 'model' => 'NLR45'],
                ['name' => 'TT-01 MACK', 'make' => 'MACK', 'model' => null],
                ['name' => 'CT-01 HINO', 'make' => 'HINO', 'model' => null],
                ['name' => 'PM-02 ISUZU PRIME MVR CXY 240-460', 'make' => 'ISUZU', 'model' => 'PRIME MVR CXY 240-460'],
            ];

            foreach ($trucks as $truckData) {
                $vehicle = Vehicle::create([
                    'vehicle_type_id' => $truck->id,
                    'status' => 'active',
                ]);

                $this->createFieldValue($vehicle, $truck, 'name', $truckData['name']);
                $this->createFieldValue($vehicle, $truck, 'make', $truckData['make']);
                if ($truckData['model']) {
                    $this->createFieldValue($vehicle, $truck, 'model', $truckData['model']);
                }
            }

            $this->command->info('✓ Trucks seeded!');
        }

        // Vans
        if ($van) {
            $vans = [
                ['name' => 'LV-05 LDV V-80 VAN', 'make' => 'LDV', 'model' => 'V-80'],
                ['name' => 'LV-04 LDV V-80 VAN', 'make' => 'LDV', 'model' => 'V-80'],
            ];

            foreach ($vans as $vanData) {
                $vehicle = Vehicle::create([
                    'vehicle_type_id' => $van->id,
                    'status' => 'active',
                ]);

                $this->createFieldValue($vehicle, $van, 'name', $vanData['name']);
                $this->createFieldValue($vehicle, $van, 'make', $vanData['make']);
                $this->createFieldValue($vehicle, $van, 'model', $vanData['model']);
            }

            $this->command->info('✓ Vans seeded!');
        }

        $this->command->info('✓ All demo vehicles seeded successfully!');
    }

    /**
     * Helper function to create field values
     */
    private function createFieldValue(Vehicle $vehicle, VehicleType $vehicleType, string $key, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $field = VehicleTypeField::where('vehicle_type_id', $vehicleType->id)
            ->where('key', $key)
            ->whereNull('tenant_id')
            ->first();

        if ($field) {
            VehicleFieldValue::create([
                'vehicle_id' => $vehicle->id,
                'vehicle_type_field_id' => $field->id,
                'value' => $value,
            ]);
        }
    }
}

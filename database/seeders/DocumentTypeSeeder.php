<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentType;
use App\Models\VehicleType;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Document types based on Cranelift screenshots - properly restricted by vehicle type
     */
    public function run(): void
    {
        $crane = VehicleType::where('name', 'Crane')->first();
        $lightVehicle = VehicleType::where('name', 'Light Vehicle')->first();
        $truck = VehicleType::where('name', 'Truck')->first();
        $van = VehicleType::where('name', 'Van')->first();

        $totalCreated = 0;

        // Crane Document Types (Screenshot 3 - AWD-05 FRANNA MAC)
        if ($crane) {
            $craneDocTypes = [
                ['name' => '10 Year Inspection', 'description' => 'Mandatory 10-year inspection certificate', 'sort_order' => 1],
                ['name' => 'Consents/Inspections', 'description' => 'Inspection consents and reports', 'sort_order' => 2],
                ['name' => 'Lifting Gear', 'description' => 'Lifting gear specifications and certifications', 'sort_order' => 3],
                ['name' => 'Load Charts', 'description' => 'Load capacity charts', 'sort_order' => 4],
                ['name' => 'Maintenance Information', 'description' => 'Maintenance records and schedules', 'sort_order' => 5],
                ['name' => 'NDT', 'description' => 'Non-Destructive Testing reports', 'sort_order' => 6],
                ['name' => 'Operating Manuals', 'description' => 'Operating instructions and manuals', 'sort_order' => 7],
                ['name' => 'Product And Installation Manual', 'description' => 'Product specifications and installation guide', 'sort_order' => 8],
                ['name' => 'Registrations', 'description' => 'Vehicle registration documents', 'sort_order' => 9],
                ['name' => 'Risk Assessment', 'description' => 'Risk assessment documentation', 'sort_order' => 10],
                ['name' => 'SWMS', 'description' => 'Safe Work Method Statements', 'sort_order' => 11],
                ['name' => 'Insurances', 'description' => 'Insurance certificates and policies', 'sort_order' => 12],
            ];

            foreach ($craneDocTypes as $docType) {
                DocumentType::create(array_merge($docType, [
                    'vehicle_type_id' => $crane->id,
                    'is_active' => true,
                ]));
                $totalCreated++;
            }
            $this->command->info('✓ Crane document types: ' . count($craneDocTypes));
        }

        // Light Vehicle Document Types (Screenshot 5 - LV-02 GREAT WALL STEED)
        if ($lightVehicle) {
            $lvDocTypes = [
                ['name' => 'Load Restraint Guide', 'description' => 'Load restraint guidelines and procedures', 'sort_order' => 1],
                ['name' => 'Maintenance Information', 'description' => 'Maintenance records and schedules', 'sort_order' => 2],
                ['name' => 'Registrations', 'description' => 'Vehicle registration documents', 'sort_order' => 3],
                ['name' => 'Insurances', 'description' => 'Insurance certificates and policies', 'sort_order' => 4],
            ];

            foreach ($lvDocTypes as $docType) {
                DocumentType::create(array_merge($docType, [
                    'vehicle_type_id' => $lightVehicle->id,
                    'is_active' => true,
                ]));
                $totalCreated++;
            }
            $this->command->info('✓ Light Vehicle document types: ' . count($lvDocTypes));
        }

        // Truck Document Types (Screenshot 7 - LV-03 ISUZU NLR45 Truck)
        if ($truck) {
            $truckDocTypes = [
                ['name' => 'Load Restraint Guide', 'description' => 'Load restraint guidelines and procedures', 'sort_order' => 1],
                ['name' => 'Maintenance Information', 'description' => 'Maintenance records and schedules', 'sort_order' => 2],
                ['name' => 'Registrations', 'description' => 'Vehicle registration documents', 'sort_order' => 3],
                ['name' => 'Insurances', 'description' => 'Insurance certificates and policies', 'sort_order' => 4],
                ['name' => 'Inductions', 'description' => 'Induction and training records', 'sort_order' => 5],
            ];

            foreach ($truckDocTypes as $docType) {
                DocumentType::create(array_merge($docType, [
                    'vehicle_type_id' => $truck->id,
                    'is_active' => true,
                ]));
                $totalCreated++;
            }
            $this->command->info('✓ Truck document types: ' . count($truckDocTypes));
        }

        // Van Document Types (Screenshot 9 - LV-05 LDV V-80 VAN)
        if ($van) {
            $vanDocTypes = [
                ['name' => 'Product And Installation Manual', 'description' => 'Product specifications and installation guide', 'sort_order' => 1],
                ['name' => 'Lifting Gear', 'description' => 'Lifting gear specifications and certifications', 'sort_order' => 2],
                ['name' => 'Registrations', 'description' => 'Vehicle registration documents', 'sort_order' => 3],
                ['name' => 'Insurances', 'description' => 'Insurance certificates and policies', 'sort_order' => 4],
            ];

            foreach ($vanDocTypes as $docType) {
                DocumentType::create(array_merge($docType, [
                    'vehicle_type_id' => $van->id,
                    'is_active' => true,
                ]));
                $totalCreated++;
            }
            $this->command->info('✓ Van document types: ' . count($vanDocTypes));
        }

        $this->command->newLine();
        $this->command->info('✓ Total document types created: ' . $totalCreated);
    }
}

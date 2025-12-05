<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DocumentType;
use App\Models\VehicleType;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ======================
        // 1. GLOBAL DOCUMENT TYPES (Apply to all vehicle types, all tenants)
        // ======================
        $globalTypes = [
            [
                'name' => 'Insurance',
                'description' => 'Vehicle insurance certificate',
                'is_required' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Registration',
                'description' => 'Vehicle registration document',
                'is_required' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'Fitness Certificate',
                'description' => 'Vehicle fitness certificate',
                'is_required' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'Tax Certificate',
                'description' => 'Vehicle tax payment certificate',
                'is_required' => false,
                'sort_order' => 40,
            ],
            [
                'name' => 'Owner Manual',
                'description' => 'Vehicle owner/operator manual',
                'is_required' => false,
                'sort_order' => 50,
            ],
        ];

        foreach ($globalTypes as $type) {
            DocumentType::create(array_merge($type, [
                'vehicle_type_id' => null, // NULL = Global
                'tenant_id' => null, // NULL = Superadmin created
                'is_active' => true,
            ]));
        }

        // ======================
        // 2. VEHICLE-TYPE SPECIFIC DOCUMENT TYPES
        // ======================

        // Find vehicle types (you may need to adjust these queries based on your data)
        $mobileCustomerCrane = VehicleType::where('name', 'like', '%Mobile%Crane%')->first();
        $towerCrane = VehicleType::where('name', 'like', '%Tower%Crane%')->first();
        $excavator = VehicleType::where('name', 'like', '%Excavator%')->first();
        $truck = VehicleType::where('name', 'like', '%Truck%')->first();

        // Mobile Crane specific documents
        if ($mobileCustomerCrane) {
            DocumentType::create([
                'name' => 'Load Test Certificate',
                'description' => 'Certificate proving crane load capacity has been tested',
                'vehicle_type_id' => $mobileCustomerCrane->id,
                'tenant_id' => null,
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 60,
            ]);

            DocumentType::create([
                'name' => 'Crane Operator License',
                'description' => 'Valid crane operator license',
                'vehicle_type_id' => $mobileCustomerCrane->id,
                'tenant_id' => null,
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 70,
            ]);
        }

        // Tower Crane specific documents
        if ($towerCrane) {
            DocumentType::create([
                'name' => 'Installation Certificate',
                'description' => 'Certificate confirming proper installation',
                'vehicle_type_id' => $towerCrane->id,
                'tenant_id' => null,
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 60,
            ]);

            DocumentType::create([
                'name' => 'Structural Integrity Report',
                'description' => 'Annual structural integrity inspection report',
                'vehicle_type_id' => $towerCrane->id,
                'tenant_id' => null,
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 70,
            ]);
        }

        // Excavator specific documents
        if ($excavator) {
            DocumentType::create([
                'name' => 'Hydraulic Inspection Certificate',
                'description' => 'Certificate of hydraulic system inspection',
                'vehicle_type_id' => $excavator->id,
                'tenant_id' => null,
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 60,
            ]);
        }

        // Truck specific documents
        if ($truck) {
            DocumentType::create([
                'name' => 'Heavy Vehicle Fitness',
                'description' => 'Heavy vehicle fitness certificate',
                'vehicle_type_id' => $truck->id,
                'tenant_id' => null,
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 60,
            ]);

            DocumentType::create([
                'name' => 'Commercial Vehicle Permit',
                'description' => 'Permit for commercial vehicle operation',
                'vehicle_type_id' => $truck->id,
                'tenant_id' => null,
                'is_required' => false,
                'is_active' => true,
                'sort_order' => 70,
            ]);
        }

        $this->command->info('✅ Document types seeded successfully!');
        $this->command->info('   - Global types: 5');
        $this->command->info('   - Vehicle-type specific types: ' . (
            ($mobileCustomerCrane ? 2 : 0) +
            ($towerCrane ? 2 : 0) +
            ($excavator ? 1 : 0) +
            ($truck ? 2 : 0)
        ));
    }
}

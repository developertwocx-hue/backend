<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ComplianceType;
use App\Models\VehicleType;
use App\Models\AustralianState;

class VictorianComplianceTypesSeeder extends Seeder
{
    /**
     * Run the database seeds for Victorian compliance types based on vehicle types.
     */
    public function run(): void
    {
        // Get Victoria state
        $victoria = AustralianState::where('code', 'VIC')->first();

        // Get all vehicle types
        $lightVehicle = VehicleType::where('name', 'Light Vehicle')->first();
        $van = VehicleType::where('name', 'Van')->first();
        $miniVan = VehicleType::where('name', 'mini van')->first();
        $truck = VehicleType::where('name', 'Truck')->first();
        $crane = VehicleType::where('name', 'Crane')->first();

        echo "Creating Victorian Compliance Types...\n";

        // ========================================
        // 1. LIGHT VEHICLE COMPLIANCE TYPES
        // ========================================
        if ($lightVehicle) {
            echo "\n--- Light Vehicle Compliance Types ---\n";

            // Vehicle Registration (Light Vehicle)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'Vehicle Registration (VIC - Light Vehicle)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $lightVehicle->id,
                ],
                [
                    'description' => 'Annual vehicle registration evidence (rego papers) for light vehicles in Victoria',
                    'category' => 'registration',
                    'tenant_id' => null, // Global
                    'renewal_frequency_days' => 365,
                    'requires_document' => true,
                    'is_required' => true,
                    'is_active' => true,
                    'alert_thresholds' => [30, 14, 7, 0],
                    'sort_order' => 10,
                ]
            );
            echo "✓ Created: Vehicle Registration (VIC - Light Vehicle)\n";

            // CTP Insurance (Light Vehicle)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'CTP Insurance (VIC - Light Vehicle)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $lightVehicle->id,
                ],
                [
                    'description' => 'Compulsory Third Party insurance (part of VicRoads registration)',
                    'category' => 'insurance',
                    'tenant_id' => null,
                    'renewal_frequency_days' => 365,
                    'requires_document' => true,
                    'is_required' => true,
                    'is_active' => true,
                    'alert_thresholds' => [30, 14, 7, 0],
                    'sort_order' => 20,
                ]
            );
            echo "✓ Created: CTP Insurance (VIC - Light Vehicle)\n";

            // Certificate of Roadworthiness (Light Vehicle)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'Certificate of Roadworthiness (VIC - Light Vehicle)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $lightVehicle->id,
                ],
                [
                    'description' => 'Required when registering, re-registering, selling, or clearing defects. Not typically required annually unless sold/re-registered.',
                    'category' => 'roadworthiness',
                    'tenant_id' => null,
                    'renewal_frequency_days' => null, // Event-based, not time-based
                    'requires_document' => true,
                    'is_required' => false, // Not always required
                    'is_active' => true,
                    'alert_thresholds' => [30, 14, 7],
                    'sort_order' => 30,
                ]
            );
            echo "✓ Created: Certificate of Roadworthiness (VIC - Light Vehicle)\n";
        }

        // ========================================
        // 2. VAN COMPLIANCE TYPES
        // ========================================
        if ($van) {
            echo "\n--- Van Compliance Types ---\n";

            // Vehicle Registration (Van)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'Vehicle Registration (VIC - Van)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $van->id,
                ],
                [
                    'description' => 'Annual vehicle registration for light commercial vans in Victoria',
                    'category' => 'registration',
                    'tenant_id' => null,
                    'renewal_frequency_days' => 365,
                    'requires_document' => true,
                    'is_required' => true,
                    'is_active' => true,
                    'alert_thresholds' => [30, 14, 7, 0],
                    'sort_order' => 40,
                ]
            );
            echo "✓ Created: Vehicle Registration (VIC - Van)\n";

            // CTP Insurance (Van)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'CTP Insurance (VIC - Van)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $van->id,
                ],
                [
                    'description' => 'Compulsory Third Party insurance for vans',
                    'category' => 'insurance',
                    'tenant_id' => null,
                    'renewal_frequency_days' => 365,
                    'requires_document' => true,
                    'is_required' => true,
                    'is_active' => true,
                    'alert_thresholds' => [30, 14, 7, 0],
                    'sort_order' => 50,
                ]
            );
            echo "✓ Created: CTP Insurance (VIC - Van)\n";

            // Certificate of Roadworthiness (Van)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'Certificate of Roadworthiness (VIC - Van)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $van->id,
                ],
                [
                    'description' => 'Required when registering, re-registering, selling, or clearing defects',
                    'category' => 'roadworthiness',
                    'tenant_id' => null,
                    'renewal_frequency_days' => null,
                    'requires_document' => true,
                    'is_required' => false,
                    'is_active' => true,
                    'alert_thresholds' => [30, 14, 7],
                    'sort_order' => 60,
                ]
            );
            echo "✓ Created: Certificate of Roadworthiness (VIC - Van)\n";

            // ADR Compliance Evidence (Van)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'ADR Compliance Evidence (VIC - Van)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $van->id,
                ],
                [
                    'description' => 'Australian Design Rules compliance documentation',
                    'category' => 'other',
                    'tenant_id' => null,
                    'renewal_frequency_days' => null,
                    'requires_document' => true,
                    'is_required' => false,
                    'is_active' => true,
                    'alert_thresholds' => [],
                    'sort_order' => 70,
                ]
            );
            echo "✓ Created: ADR Compliance Evidence (VIC - Van)\n";
        }

        // Handle mini van same as van
        if ($miniVan) {
            echo "\n--- Mini Van Compliance Types ---\n";

            ComplianceType::updateOrCreate(
                ['name' => 'Vehicle Registration (VIC - Mini Van)', 'state_code' => 'VIC', 'vehicle_type_id' => $miniVan->id],
                ['description' => 'Annual vehicle registration for mini vans in Victoria', 'category' => 'registration', 'tenant_id' => null, 'renewal_frequency_days' => 365, 'requires_document' => true, 'is_required' => true, 'is_active' => true, 'alert_thresholds' => [30, 14, 7, 0], 'sort_order' => 75]
            );
            echo "✓ Created: Vehicle Registration (VIC - Mini Van)\n";

            ComplianceType::updateOrCreate(
                ['name' => 'CTP Insurance (VIC - Mini Van)', 'state_code' => 'VIC', 'vehicle_type_id' => $miniVan->id],
                ['description' => 'Compulsory Third Party insurance for mini vans', 'category' => 'insurance', 'tenant_id' => null, 'renewal_frequency_days' => 365, 'requires_document' => true, 'is_required' => true, 'is_active' => true, 'alert_thresholds' => [30, 14, 7, 0], 'sort_order' => 76]
            );
            echo "✓ Created: CTP Insurance (VIC - Mini Van)\n";
        }

        // ========================================
        // 3. TRUCK COMPLIANCE TYPES (Heavy Vehicle >4.5t GVM)
        // ========================================
        if ($truck) {
            echo "\n--- Truck (Heavy Vehicle) Compliance Types ---\n";

            // Heavy Vehicle Registration (Truck)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'Heavy Vehicle Registration (VIC - Truck)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $truck->id,
                ],
                [
                    'description' => 'Heavy vehicle registration with VicRoads/NHVR for trucks over 4.5t GVM',
                    'category' => 'registration',
                    'tenant_id' => null,
                    'renewal_frequency_days' => 365,
                    'requires_document' => true,
                    'is_required' => true,
                    'is_active' => true,
                    'alert_thresholds' => [30, 14, 7, 0],
                    'sort_order' => 80,
                ]
            );
            echo "✓ Created: Heavy Vehicle Registration (VIC - Truck)\n";

            // CTP Insurance (Truck)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'CTP Insurance (VIC - Truck)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $truck->id,
                ],
                [
                    'description' => 'CTP insurance (part of heavy vehicle registration)',
                    'category' => 'insurance',
                    'tenant_id' => null,
                    'renewal_frequency_days' => 365,
                    'requires_document' => true,
                    'is_required' => true,
                    'is_active' => true,
                    'alert_thresholds' => [30, 14, 7, 0],
                    'sort_order' => 90,
                ]
            );
            echo "✓ Created: CTP Insurance (VIC - Truck)\n";

            // Compliance Plate / National Standards Evidence (Truck)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'Compliance Plate / National Standards (VIC - Truck)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $truck->id,
                ],
                [
                    'description' => 'Compliance plate or national standards evidence (ADRs compliance)',
                    'category' => 'other',
                    'tenant_id' => null,
                    'renewal_frequency_days' => null,
                    'requires_document' => true,
                    'is_required' => true,
                    'is_active' => true,
                    'alert_thresholds' => [],
                    'sort_order' => 100,
                ]
            );
            echo "✓ Created: Compliance Plate / National Standards (VIC - Truck)\n";

            // VASS Certification (Truck - if modified)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'VASS Certification (VIC - Truck)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $truck->id,
                ],
                [
                    'description' => 'Vehicle Assessment Signatory Scheme certification for modified or non-standard trucks',
                    'category' => 'inspection',
                    'tenant_id' => null,
                    'renewal_frequency_days' => null,
                    'requires_document' => true,
                    'is_required' => false, // Only if modified
                    'is_active' => true,
                    'alert_thresholds' => [],
                    'sort_order' => 110,
                ]
            );
            echo "✓ Created: VASS Certification (VIC - Truck)\n";

            // GVM Compliance Evidence (Truck)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'GVM Compliance Evidence (VIC - Truck)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $truck->id,
                ],
                [
                    'description' => 'Gross Vehicle Mass compliance documentation',
                    'category' => 'other',
                    'tenant_id' => null,
                    'renewal_frequency_days' => null,
                    'requires_document' => true,
                    'is_required' => true,
                    'is_active' => true,
                    'alert_thresholds' => [],
                    'sort_order' => 120,
                ]
            );
            echo "✓ Created: GVM Compliance Evidence (VIC - Truck)\n";
        }

        // ========================================
        // 4. CRANE COMPLIANCE TYPES (Mobile Cranes / Heavy Work Machines)
        // ========================================
        if ($crane) {
            echo "\n--- Crane (Mobile Crane / Heavy Work Machine) Compliance Types ---\n";

            // Heavy Vehicle Registration (Crane)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'Heavy Vehicle Registration (VIC - Crane)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $crane->id,
                ],
                [
                    'description' => 'Heavy vehicle registration with VicRoads/NHVR for cranes on public roads',
                    'category' => 'registration',
                    'tenant_id' => null,
                    'renewal_frequency_days' => 365,
                    'requires_document' => true,
                    'is_required' => true,
                    'is_active' => true,
                    'alert_thresholds' => [30, 14, 7, 0],
                    'sort_order' => 130,
                ]
            );
            echo "✓ Created: Heavy Vehicle Registration (VIC - Crane)\n";

            // CTP Insurance (Crane)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'CTP Insurance (VIC - Crane)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $crane->id,
                ],
                [
                    'description' => 'CTP insurance via heavy vehicle registration',
                    'category' => 'insurance',
                    'tenant_id' => null,
                    'renewal_frequency_days' => 365,
                    'requires_document' => true,
                    'is_required' => true,
                    'is_active' => true,
                    'alert_thresholds' => [30, 14, 7, 0],
                    'sort_order' => 140,
                ]
            );
            echo "✓ Created: CTP Insurance (VIC - Crane)\n";

            // Compliance Plate / Heavy Vehicle Documentation (Crane)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'Compliance Plate / Heavy Vehicle Documentation (VIC - Crane)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $crane->id,
                ],
                [
                    'description' => 'Compliance plate or approved heavy vehicle documentation for cranes',
                    'category' => 'other',
                    'tenant_id' => null,
                    'renewal_frequency_days' => null,
                    'requires_document' => true,
                    'is_required' => true,
                    'is_active' => true,
                    'alert_thresholds' => [],
                    'sort_order' => 150,
                ]
            );
            echo "✓ Created: Compliance Plate / Heavy Vehicle Documentation (VIC - Crane)\n";

            // Weighbridge Certificate (Crane)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'Weighbridge Certificate (VIC - Crane)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $crane->id,
                ],
                [
                    'description' => 'Weighbridge certificate or evidence of exemptions if applicable',
                    'category' => 'other',
                    'tenant_id' => null,
                    'renewal_frequency_days' => 365, // Annual verification recommended
                    'requires_document' => true,
                    'is_required' => false, // Only if applicable
                    'is_active' => true,
                    'alert_thresholds' => [30, 14, 7],
                    'sort_order' => 160,
                ]
            );
            echo "✓ Created: Weighbridge Certificate (VIC - Crane)\n";

            // NHVR Standards Compliance (Crane)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'NHVR Standards Compliance (VIC - Crane)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $crane->id,
                ],
                [
                    'description' => 'National Heavy Vehicle Regulator standards compliance documentation (for non-ADR compliant cranes)',
                    'category' => 'other',
                    'tenant_id' => null,
                    'renewal_frequency_days' => null,
                    'requires_document' => true,
                    'is_required' => false,
                    'is_active' => true,
                    'alert_thresholds' => [],
                    'sort_order' => 170,
                ]
            );
            echo "✓ Created: NHVR Standards Compliance (VIC - Crane)\n";

            // SafeWork Victoria Compliance (Crane)
            ComplianceType::updateOrCreate(
                [
                    'name' => 'SafeWork Victoria Compliance (VIC - Crane)',
                    'state_code' => 'VIC',
                    'vehicle_type_id' => $crane->id,
                ],
                [
                    'description' => 'Workplace safety and AS standards compliance documentation (separate from VicRoads road requirements)',
                    'category' => 'inspection',
                    'tenant_id' => null,
                    'renewal_frequency_days' => 365,
                    'requires_document' => true,
                    'is_required' => true,
                    'is_active' => true,
                    'alert_thresholds' => [30, 14, 7, 0],
                    'sort_order' => 180,
                ]
            );
            echo "✓ Created: SafeWork Victoria Compliance (VIC - Crane)\n";
        }

        echo "\n✅ Victorian compliance types seeded successfully!\n";

        // Display summary
        $total = ComplianceType::where('state_code', 'VIC')->count();
        echo "\nTotal Victorian compliance types: {$total}\n";
    }
}
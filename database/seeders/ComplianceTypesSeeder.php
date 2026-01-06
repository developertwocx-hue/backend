<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ComplianceType;

class ComplianceTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Global Compliance Types (apply to all vehicles, all states)
        $globalTypes = [
            [
                'name' => 'Vehicle Registration',
                'description' => 'Valid vehicle registration with state authority',
                'category' => 'registration',
                'vehicle_type_id' => null,
                'state_code' => null,
                'tenant_id' => null,
                'renewal_frequency_days' => 365,
                'requires_document' => true,
                'accepted_document_types' => null, // Accept any document type
                'is_required' => true,
                'is_active' => true,
                'alert_thresholds' => [30, 14, 7, 0],
                'sort_order' => 10,
            ],
            [
                'name' => 'Compulsory Third Party Insurance',
                'description' => 'CTP (Green Slip) insurance coverage',
                'category' => 'insurance',
                'vehicle_type_id' => null,
                'state_code' => null,
                'tenant_id' => null,
                'renewal_frequency_days' => 365,
                'requires_document' => true,
                'accepted_document_types' => null,
                'is_required' => true,
                'is_active' => true,
                'alert_thresholds' => [30, 14, 7, 0],
                'sort_order' => 20,
            ],
        ];

        // State-Specific: NSW Green Sticker
        $nswTypes = [
            [
                'name' => 'Heavy Vehicle Inspection (NSW)',
                'description' => 'NSW Heavy Vehicle Inspection Scheme (HVIS) - Green Sticker',
                'category' => 'roadworthiness',
                'vehicle_type_id' => null, // Apply to all heavy vehicles
                'state_code' => 'NSW',
                'tenant_id' => null,
                'renewal_frequency_days' => 365,
                'requires_document' => true,
                'accepted_document_types' => null,
                'is_required' => true,
                'is_active' => true,
                'alert_thresholds' => [30, 14, 7, 0],
                'sort_order' => 30,
            ],
        ];

        // State-Specific: Victoria
        $vicTypes = [
            [
                'name' => 'Roadworthiness Certificate (VIC)',
                'description' => 'Victoria annual roadworthiness inspection for heavy vehicles',
                'category' => 'roadworthiness',
                'vehicle_type_id' => null,
                'state_code' => 'VIC',
                'tenant_id' => null,
                'renewal_frequency_days' => 365,
                'requires_document' => true,
                'accepted_document_types' => null,
                'is_required' => true,
                'is_active' => true,
                'alert_thresholds' => [30, 14, 7, 0],
                'sort_order' => 30,
            ],
        ];

        // State-Specific: Queensland
        $qldTypes = [
            [
                'name' => 'Safety Certificate (QLD)',
                'description' => 'Queensland annual safety certificate for heavy vehicles',
                'category' => 'roadworthiness',
                'vehicle_type_id' => null,
                'state_code' => 'QLD',
                'tenant_id' => null,
                'renewal_frequency_days' => 365,
                'requires_document' => true,
                'accepted_document_types' => null,
                'is_required' => true,
                'is_active' => true,
                'alert_thresholds' => [30, 14, 7, 0],
                'sort_order' => 30,
            ],
        ];

        // Optional: Periodic Safety Inspections
        $optionalTypes = [
            [
                'name' => '6-Month Safety Inspection',
                'description' => 'Optional 6-monthly safety inspection for fleet management',
                'category' => 'inspection',
                'vehicle_type_id' => null,
                'state_code' => null,
                'tenant_id' => null,
                'renewal_frequency_days' => 180,
                'requires_document' => true,
                'accepted_document_types' => null,
                'is_required' => false, // Optional
                'is_active' => true,
                'alert_thresholds' => [14, 7, 0],
                'sort_order' => 100,
            ],
        ];

        // Combine all types
        $allTypes = array_merge(
            $globalTypes,
            $nswTypes,
            $vicTypes,
            $qldTypes,
            $optionalTypes
        );

        // Seed the compliance types
        foreach ($allTypes as $type) {
            ComplianceType::updateOrCreate(
                [
                    'name' => $type['name'],
                    'state_code' => $type['state_code'],
                    'vehicle_type_id' => $type['vehicle_type_id'],
                ],
                $type
            );
        }

        $this->command->info('Compliance types seeded successfully!');
        $this->command->info('✓ Global types: ' . count($globalTypes));
        $this->command->info('✓ NSW types: ' . count($nswTypes));
        $this->command->info('✓ VIC types: ' . count($vicTypes));
        $this->command->info('✓ QLD types: ' . count($qldTypes));
        $this->command->info('✓ Optional types: ' . count($optionalTypes));
        $this->command->info('Total: ' . count($allTypes) . ' compliance types');
    }
}
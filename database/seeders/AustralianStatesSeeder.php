<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AustralianState;

class AustralianStatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $states = [
            [
                'code' => 'NSW',
                'name' => 'New South Wales',
                'has_green_sticker_requirement' => true,
                'green_sticker_frequency_days' => 365,
                'notes' => 'Heavy Vehicle Inspection Scheme (HVIS) required annually for heavy vehicles.',
            ],
            [
                'code' => 'VIC',
                'name' => 'Victoria',
                'has_green_sticker_requirement' => true,
                'green_sticker_frequency_days' => 365,
                'notes' => 'Annual roadworthiness inspection required for heavy commercial vehicles.',
            ],
            [
                'code' => 'QLD',
                'name' => 'Queensland',
                'has_green_sticker_requirement' => true,
                'green_sticker_frequency_days' => 365,
                'notes' => 'Annual safety certificate required for heavy vehicles.',
            ],
            [
                'code' => 'WA',
                'name' => 'Western Australia',
                'has_green_sticker_requirement' => true,
                'green_sticker_frequency_days' => 365,
                'notes' => 'Annual inspection required for commercial vehicles.',
            ],
            [
                'code' => 'SA',
                'name' => 'South Australia',
                'has_green_sticker_requirement' => true,
                'green_sticker_frequency_days' => 365,
                'notes' => 'Annual roadworthiness inspection for heavy vehicles.',
            ],
            [
                'code' => 'TAS',
                'name' => 'Tasmania',
                'has_green_sticker_requirement' => true,
                'green_sticker_frequency_days' => 365,
                'notes' => 'Annual safety inspection required.',
            ],
            [
                'code' => 'ACT',
                'name' => 'Australian Capital Territory',
                'has_green_sticker_requirement' => false,
                'green_sticker_frequency_days' => null,
                'notes' => 'Different inspection requirements apply.',
            ],
            [
                'code' => 'NT',
                'name' => 'Northern Territory',
                'has_green_sticker_requirement' => true,
                'green_sticker_frequency_days' => 365,
                'notes' => 'Annual roadworthiness inspection required.',
            ],
        ];

        foreach ($states as $state) {
            AustralianState::updateOrCreate(
                ['code' => $state['code']],
                $state
            );
        }

        $this->command->info('Australian states seeded successfully!');
    }
}
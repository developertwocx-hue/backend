<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');
        $this->command->newLine();

        // Global seeders (no tenant context required)
        $this->command->info('📦 Seeding global data...');
        $this->call([
            VehicleTypeSeeder::class,
            VehicleTypeFieldSeeder::class,
            DocumentTypeSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('✅ Global seeding completed!');
        $this->command->newLine();

        // Note about tenant-specific seeders
        $this->command->warn('⚠️  Tenant-specific data NOT seeded.');
        $this->command->info('To seed demo vehicles for a specific tenant, run:');
        $this->command->line('   php artisan tenants:seed --tenants=<tenant-id> --class=DemoVehicleSeeder');
        $this->command->newLine();
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create a superadmin tenant
        $tenant = Tenant::firstOrCreate(
            ['email' => 'superadmin@cranelift.com'],
            [
                'name' => 'Cranelift SuperAdmin',
                'phone' => '+1234567890',
                'address' => 'Admin Office',
                'subscription_plan' => 'enterprise',
                'subscription_ends_at' => now()->addYears(10),
            ]
        );

        // Create superadmin user
        $superadmin = User::firstOrCreate(
            ['email' => 'admin@cranelift.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
                'tenant_id' => $tenant->id,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✅ Superadmin created successfully!');
        $this->command->info('');
        $this->command->info('📧 Email: admin@cranelift.com');
        $this->command->info('🔑 Password: password');
        $this->command->info('');
        $this->command->warn('⚠️  Please change the password after first login!');
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create superadmin user WITHOUT tenant (superadmin manages all tenants)
        $superadmin = User::firstOrCreate(
            ['email' => 'admin@cranelift.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'superadmin',
                'tenant_id' => null,  // Superadmin is not tied to any tenant
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✅ Superadmin created successfully!');
        $this->command->info('');
        $this->command->info('📧 Email: admin@cranelift.com');
        $this->command->info('🔑 Password: password');
        $this->command->info('🔐 Role: superadmin');
        $this->command->info('🏢 Tenant: None (manages all tenants)');
        $this->command->info('');
        $this->command->warn('⚠️  Please change the password after first login!');
    }
}

<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'address',
        'subscription_plan',
        'subscription_ends_at',
    ];

    protected $casts = [
        'subscription_ends_at' => 'datetime',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'email',
            'phone',
            'address',
            'subscription_plan',
            'subscription_ends_at',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function vehicleTypes()
    {
        // Get vehicle types that are used by this tenant's vehicles
        return $this->hasManyThrough(
            VehicleType::class,
            Vehicle::class,
            'tenant_id',      // Foreign key on vehicles table
            'id',             // Foreign key on vehicle_types table
            'id',             // Local key on tenants table
            'vehicle_type_id' // Local key on vehicles table
        )->distinct();
    }
}

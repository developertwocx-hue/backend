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
}

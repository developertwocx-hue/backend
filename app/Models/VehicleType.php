<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class VehicleType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function fields()
    {
        return $this->hasMany(VehicleTypeField::class);
    }

    public function defaultFields()
    {
        return $this->hasMany(VehicleTypeField::class)->whereNull('tenant_id');
    }

    public function customFields($tenantId)
    {
        return $this->hasMany(VehicleTypeField::class)->where('tenant_id', $tenantId);
    }
}

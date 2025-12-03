<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'vehicle_type_id',
        'status',
    ];

    protected $casts = [
        //
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function documents()
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function fieldValues()
    {
        return $this->hasMany(VehicleFieldValue::class);
    }
}

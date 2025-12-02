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
        'name',
        'make',
        'model',
        'year',
        'registration_number',
        'vin',
        'serial_number',
        'capacity',
        'capacity_unit',
        'specifications',
        'status',
        'purchase_date',
        'purchase_price',
        'last_service_date',
        'next_service_date',
        'notes',
    ];

    protected $casts = [
        'capacity' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'purchase_date' => 'date',
        'last_service_date' => 'date',
        'next_service_date' => 'date',
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
}

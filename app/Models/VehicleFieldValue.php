<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'vehicle_type_field_id',
        'value',
    ];

    /**
     * Relationship: Belongs to Vehicle
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Relationship: Belongs to VehicleTypeField
     */
    public function field()
    {
        return $this->belongsTo(VehicleTypeField::class, 'vehicle_type_field_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AustralianState extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'has_green_sticker_requirement',
        'green_sticker_frequency_days',
        'notes',
    ];

    protected $casts = [
        'has_green_sticker_requirement' => 'boolean',
        'green_sticker_frequency_days' => 'integer',
    ];

    // Relationships
    public function complianceTypes()
    {
        return $this->hasMany(ComplianceType::class, 'state_code', 'code');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'state_of_operation', 'code');
    }
}
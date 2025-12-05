<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class VehicleDocument extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'document_type_id',
        'document_type', // Legacy field, will be deprecated
        'document_name',
        'document_number',
        'file_path',
        'file_type',
        'file_size',
        'issue_date',
        'expiry_date',
        'is_expired',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'is_expired' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * Boot method to add model event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-fill legacy document_type field from DocumentType relationship
        static::saving(function ($vehicleDocument) {
            if ($vehicleDocument->document_type_id && !$vehicleDocument->document_type) {
                $documentType = DocumentType::find($vehicleDocument->document_type_id);
                if ($documentType) {
                    $vehicleDocument->document_type = $documentType->name;
                }
            }
        });
    }
}

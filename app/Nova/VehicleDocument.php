<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\NovaRequest;

class VehicleDocument extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\VehicleDocument>
     */
    public static $model = \App\Models\VehicleDocument::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'document_name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'document_name', 'document_number', 'document_type',
    ];

    /**
     * Determine if the resource should be available for the given request.
     */
    public static function authorizedToViewAny($request): bool
    {
        return true;
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Tenant')
                ->sortable()
                ->rules('required')
                ->withoutTrashed()
                ->searchable(),

            BelongsTo::make('Vehicle')
                ->sortable()
                ->rules('required')
                ->withoutTrashed()
                ->searchable()
                ->dependsOn(['tenant'], function (BelongsTo $field, NovaRequest $request, $formData) {
                    // Always apply the query filter based on selected tenant
                    $field->relatableQueryUsing(function (NovaRequest $request, $query) use ($formData) {
                        if (!empty($formData->tenant)) {
                            return $query->where('tenant_id', $formData->tenant);
                        }
                        return $query;
                    });
                }),

            BelongsTo::make('Document Type', 'documentType', DocumentType::class)
                ->sortable()
                ->rules('required')
                ->withoutTrashed()
                ->searchable()
                ->help('Select a vehicle first to filter document types')
                ->displayUsing(function ($documentType) {
                    return $documentType->name . ' (' . $documentType->scope_type . ')';
                })
                ->relatableQueryUsing(function (NovaRequest $request, $query) {
                    // Get vehicle_id from request
                    $vehicleId = $request->get('vehicle') ?? $request->get('viaResourceId');

                    if ($vehicleId) {
                        $vehicle = \App\Models\Vehicle::find($vehicleId);

                        if ($vehicle) {
                            return $query->where('is_active', true)
                                ->where(function ($q) use ($vehicle) {
                                    // Global types (no tenant, no vehicle type)
                                    $q->where(function ($subQ) {
                                        $subQ->whereNull('tenant_id')->whereNull('vehicle_type_id');
                                    })
                                    // Vehicle-type specific (no tenant, matching vehicle type)
                                    ->orWhere(function ($subQ) use ($vehicle) {
                                        $subQ->whereNull('tenant_id')->where('vehicle_type_id', $vehicle->vehicle_type_id);
                                    })
                                    // Tenant custom for all types (matching tenant, no vehicle type)
                                    ->orWhere(function ($subQ) use ($vehicle) {
                                        $subQ->where('tenant_id', $vehicle->tenant_id)->whereNull('vehicle_type_id');
                                    })
                                    // Tenant custom for specific type (matching tenant and vehicle type)
                                    ->orWhere(function ($subQ) use ($vehicle) {
                                        $subQ->where('tenant_id', $vehicle->tenant_id)->where('vehicle_type_id', $vehicle->vehicle_type_id);
                                    });
                                })
                                ->orderBy('sort_order')
                                ->orderBy('name');
                        }
                    }

                    // Default: show all active document types
                    return $query->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('name');
                }),

            Text::make('Document Name', 'document_name')
                ->sortable()
                ->rules('required', 'max:255')
                ->help('Descriptive name for this document'),

            Text::make('Document Number', 'document_number')
                ->sortable()
                ->rules('nullable', 'max:255')
                ->help('Official document number or reference'),

            File::make('Document File', 'file_path')
                ->disk('public')
                ->path('vehicle-documents')
                ->prunable()
                ->rules('nullable', 'file', 'max:10240')
                ->help('Upload document file (max 10MB)'),

            Text::make('File Type', 'file_type')
                ->hideFromIndex()
                ->rules('nullable', 'max:50')
                ->help('Automatically detected file type'),

            Number::make('File Size', 'file_size')
                ->hideFromIndex()
                ->rules('nullable', 'integer', 'min:0')
                ->help('File size in bytes'),

            Date::make('Issue Date', 'issue_date')
                ->sortable()
                ->rules('nullable', 'date'),

            Date::make('Expiry Date', 'expiry_date')
                ->sortable()
                ->rules('nullable', 'date')
                ->help('Leave empty if document does not expire'),

            Boolean::make('Is Expired', 'is_expired')
                ->sortable()
                ->readonly()
                ->help('Automatically calculated based on expiry date'),

            Textarea::make('Notes')
                ->hideFromIndex()
                ->rules('nullable')
                ->help('Additional notes about this document'),

            BelongsTo::make('Uploaded By', 'uploadedBy', User::class)
                ->sortable()
                ->nullable()
                ->withoutTrashed(),

            DateTime::make('Created At')
                ->exceptOnForms()
                ->sortable(),

            DateTime::make('Updated At')
                ->exceptOnForms(),
        ];
    }

    /**
     * Get the cards available for the resource.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, \Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, \Laravel\Nova\Lenses\Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, \Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}

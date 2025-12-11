<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\NovaRequest;

class VehicleTypeField extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\VehicleTypeField>
     */
    public static $model = \App\Models\VehicleTypeField::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'name', 'key', 'description',
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

            BelongsTo::make('Vehicle Type', 'vehicleType', VehicleType::class)
                ->sortable()
                ->rules('required')
                ->withoutTrashed()
                ->help('The vehicle type this field belongs to'),

            Text::make('Field Type', function() {
                return $this->tenant_id ? 'Custom (Tenant)' : 'Default (Superadmin)';
            })
                ->exceptOnForms()
                ->sortable(),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255')
                ->help('Display name for this field (e.g., "Bed Capacity", "GPS Tracker ID")'),

            Text::make('Key')
                ->sortable()
                ->rules('required', 'max:255', 'regex:/^[a-z0-9_]+$/')
                ->help('Machine-readable key (lowercase, numbers, underscores only, e.g., "bed_capacity")'),

            Select::make('Field Type', 'field_type')
                ->options([
                    'text' => 'Text',
                    'number' => 'Number',
                    'date' => 'Date',
                    'select' => 'Select Dropdown',
                    'boolean' => 'Yes/No',
                    'textarea' => 'Long Text',
                ])
                ->displayUsingLabels()
                ->sortable()
                ->rules('required')
                ->help('Type of input field'),

            Text::make('Unit')
                ->hideFromIndex()
                ->rules('nullable', 'max:50')
                ->help('Unit of measurement (e.g., "tons", "meters", "kg")'),

            KeyValue::make('Options')
                ->rules('nullable', 'json')
                ->help('For "Select Dropdown" type: enter options as key-value pairs')
                ->hideFromIndex(),

            Boolean::make('Is Required', 'is_required')
                ->sortable()
                ->default(false)
                ->help('Whether this field must be filled'),

            Boolean::make('Is Active', 'is_active')
                ->sortable()
                ->default(true)
                ->help('Whether this field is currently active'),

            Number::make('Sort Order', 'sort_order')
                ->sortable()
                ->default(0)
                ->help('Display order (lower numbers appear first)'),

            Textarea::make('Description')
                ->hideFromIndex()
                ->rules('nullable')
                ->help('Optional description or instructions for this field'),

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
        return [
            new Filters\VehicleTypeFilterForFields,
            new Filters\FieldTypeFilter,
            new Filters\FieldScopeFilter,
            new Filters\IsRequiredFilter,
            new Filters\IsActiveFilter,
        ];
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

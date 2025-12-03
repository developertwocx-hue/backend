<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\NovaRequest;

class Vehicle extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Vehicle>
     */
    public static $model = \App\Models\Vehicle::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * Get the value that should be displayed to represent the resource.
     */
    public function title()
    {
        return ($this->vehicleType->name ?? 'Unknown') . ' #' . $this->id;
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
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
        $fields = [
            ID::make()->sortable(),

            BelongsTo::make('Tenant')
                ->sortable()
                ->rules('required')
                ->withoutTrashed(),

            BelongsTo::make('Vehicle Type', 'vehicleType', VehicleType::class)
                ->sortable()
                ->rules('required')
                ->withoutTrashed(),

            Text::make('Details', function() {
                // Show first 3 field values in index
                $values = $this->fieldValues()
                    ->with('field')
                    ->limit(3)
                    ->get()
                    ->map(function($fv) {
                        $name = $fv->field->name ?? 'Field';
                        $value = $fv->value;
                        $unit = $fv->field->unit ?? '';
                        return $name . ': ' . $value . ($unit ? ' ' . $unit : '');
                    })
                    ->implode(', ');

                return $values ?: 'No data';
            })->onlyOnIndex(),

            Select::make('Status')
                ->options([
                    'active' => 'Active',
                    'maintenance' => 'Maintenance',
                    'inactive' => 'Inactive',
                    'sold' => 'Sold',
                ])
                ->displayUsingLabels()
                ->sortable()
                ->rules('required')
                ->default('active'),
        ];

        // Add dynamic fields from vehicle_type_fields (only default fields for Nova/superadmin)
        // For editing: load fields based on vehicle's type
        // For creating: load fields for ALL types and show/hide based on selection

        if ($request->isCreateOrAttachRequest()) {
            // On create: Get all vehicle types and their fields
            $vehicleTypes = \App\Models\VehicleType::where('is_active', true)->get();

            foreach ($vehicleTypes as $vehicleType) {
                $typeFields = \App\Models\VehicleTypeField::where('vehicle_type_id', $vehicleType->id)
                    ->whereNull('tenant_id') // Only default fields
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get();

                foreach ($typeFields as $typeField) {
                    $field = $this->makeTypeField($typeField, $request, $vehicleType->id);
                    if ($field) {
                        // Hide from index - only show on detail/forms
                        $field->hideFromIndex();

                        // Make field dependent on vehicle_type selection
                        $field->dependsOn(['vehicleType'], function ($field, $request, $formData) use ($vehicleType, $typeField) {
                            if (isset($formData->vehicleType) && $formData->vehicleType == $vehicleType->id) {
                                $field->show();
                                // Re-apply rules only when visible
                                if ($typeField->is_required) {
                                    // For number fields, include numeric rule
                                    if ($typeField->field_type === 'number') {
                                        $field->rules('required', 'numeric');
                                    } elseif ($typeField->field_type === 'date') {
                                        $field->rules('required', 'date');
                                    } else {
                                        $field->rules('required');
                                    }
                                }
                            } else {
                                $field->hide();
                                // Remove all validation when hidden
                                $field->rules([]);
                            }
                        });
                        $fields[] = $field;
                    }
                }
            }
        } else {
            // On edit: load fields for this vehicle's type only
            $vehicleTypeId = $this->vehicle_type_id;
            if ($vehicleTypeId) {
                $typeFields = \App\Models\VehicleTypeField::where('vehicle_type_id', $vehicleTypeId)
                    ->whereNull('tenant_id') // Only default fields
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get();

                foreach ($typeFields as $typeField) {
                    $field = $this->makeTypeField($typeField, $request);
                    if ($field) {
                        // Hide from index - only show on detail/forms
                        $field->hideFromIndex();
                        $fields[] = $field;
                    }
                }
            }
        }

        $fields[] = HasMany::make('Documents', 'documents', VehicleDocument::class);
        $fields[] = DateTime::make('Created At')->exceptOnForms()->sortable();
        $fields[] = DateTime::make('Updated At')->exceptOnForms();

        return $fields;
    }

    /**
     * Create a Nova field based on the vehicle type field definition
     */
    protected function makeTypeField($typeField, $request, $vehicleTypeId = null)
    {
        // Make key unique per vehicle type to avoid conflicts
        $vehicleTypeId = $vehicleTypeId ?? $typeField->vehicle_type_id;
        $key = 'field_' . $vehicleTypeId . '_' . $typeField->key;

        // Start with nullable - rules will be applied conditionally via dependsOn
        $rules = ['nullable'];

        $label = $typeField->name;
        if ($typeField->unit) {
            $label .= ' (' . $typeField->unit . ')';
        }

        switch ($typeField->field_type) {
            case 'text':
                return Text::make($label, $key)
                    ->help($typeField->description)
                    ->rules('nullable') // Base rule, will be overridden by dependsOn
                    ->fillUsing(function () {
                        // Do nothing - handled in afterCreate/afterUpdate
                    })
                    ->resolveUsing(function ($value, $resource) use ($typeField) {
                        return $this->getFieldValue($resource, $typeField->id);
                    });

            case 'number':
                return Number::make($label, $key)
                    ->help($typeField->description)
                    ->step(0.01)
                    ->rules('nullable', 'numeric') // Base rules
                    ->fillUsing(function () {
                        // Do nothing - handled in afterCreate/afterUpdate
                    })
                    ->resolveUsing(function ($value, $resource) use ($typeField) {
                        return $this->getFieldValue($resource, $typeField->id);
                    });

            case 'date':
                return Date::make($label, $key)
                    ->help($typeField->description)
                    ->rules('nullable', 'date') // Base rules
                    ->fillUsing(function () {
                        // Do nothing - handled in afterCreate/afterUpdate
                    })
                    ->resolveUsing(function ($value, $resource) use ($typeField) {
                        return $this->getFieldValue($resource, $typeField->id);
                    });

            case 'select':
                return Select::make($label, $key)
                    ->options($typeField->options ?? [])
                    ->displayUsingLabels()
                    ->help($typeField->description)
                    ->rules('nullable') // Base rule
                    ->fillUsing(function () {
                        // Do nothing - handled in afterCreate/afterUpdate
                    })
                    ->resolveUsing(function ($value, $resource) use ($typeField) {
                        return $this->getFieldValue($resource, $typeField->id);
                    });

            case 'boolean':
                return Boolean::make($label, $key)
                    ->help($typeField->description)
                    ->rules('nullable') // Base rule
                    ->fillUsing(function () {
                        // Do nothing - handled in afterCreate/afterUpdate
                    })
                    ->resolveUsing(function ($value, $resource) use ($typeField) {
                        return $this->getFieldValue($resource, $typeField->id) === '1';
                    });

            case 'textarea':
                return Textarea::make($label, $key)
                    ->help($typeField->description)
                    ->rules('nullable') // Base rule
                    ->fillUsing(function () {
                        // Do nothing - handled in afterCreate/afterUpdate
                    })
                    ->resolveUsing(function ($value, $resource) use ($typeField) {
                        return $this->getFieldValue($resource, $typeField->id);
                    });
        }

        return null;
    }

    /**
     * Get field value from vehicle_field_values
     */
    protected function getFieldValue($resource, $fieldId)
    {
        if (!$resource || !$resource->id) {
            return null;
        }

        $fieldValue = \App\Models\VehicleFieldValue::where('vehicle_id', $resource->id)
            ->where('vehicle_type_field_id', $fieldId)
            ->first();

        return $fieldValue->value ?? null;
    }

    /**
     * Handle model events after create
     */
    public static function afterCreate(NovaRequest $request, $model)
    {
        static::saveFieldValues($request, $model);
    }

    /**
     * Handle model events after update
     */
    public static function afterUpdate(NovaRequest $request, $model)
    {
        static::saveFieldValues($request, $model);
    }

    /**
     * Save all field values for vehicle type fields
     */
    protected static function saveFieldValues($request, $model)
    {
        if (!$model->vehicle_type_id) {
            return;
        }

        // Get all default fields (tenant_id = NULL) for this vehicle type
        $typeFields = \App\Models\VehicleTypeField::where('vehicle_type_id', $model->vehicle_type_id)
            ->whereNull('tenant_id') // Only default fields for Nova
            ->where('is_active', true)
            ->get();

        foreach ($typeFields as $typeField) {
            // Key format: field_{vehicle_type_id}_{field_key}
            $key = 'field_' . $typeField->vehicle_type_id . '_' . $typeField->key;
            $value = $request->get($key);

            if ($value !== null) {
                \App\Models\VehicleFieldValue::updateOrCreate(
                    [
                        'vehicle_id' => $model->id,
                        'vehicle_type_field_id' => $typeField->id,
                    ],
                    [
                        'value' => $value,
                    ]
                );
            }
        }
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

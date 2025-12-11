<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Models\Vehicle;

class VehicleFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @var string
     */
    public $name = 'Vehicle';

    /**
     * Apply the filter to the given query.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->where('vehicle_id', $value);
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        $vehicles = Vehicle::with(['vehicleType', 'fieldValues'])
            ->orderBy('id', 'desc')
            ->limit(100) // Limit to recent 100 vehicles for performance
            ->get();

        $options = [];
        foreach ($vehicles as $vehicle) {
            // Get a display name from field values (e.g., VIN, License Plate, or Name)
            $displayName = $this->getVehicleDisplayName($vehicle);
            $label = "#{$vehicle->id} - {$displayName} ({$vehicle->vehicleType->name})";
            $options[$label] = $vehicle->id;
        }

        return $options;
    }

    /**
     * Get a meaningful display name for the vehicle
     */
    private function getVehicleDisplayName($vehicle)
    {
        // Try to find common identifying fields
        $priorities = ['vin', 'license_plate', 'registration_number', 'name', 'model'];

        foreach ($priorities as $fieldKey) {
            $fieldValue = $vehicle->fieldValues->firstWhere('vehicle_type_field.key', $fieldKey);
            if ($fieldValue && !empty($fieldValue->value)) {
                return $fieldValue->value;
            }
        }

        // If no identifying field found, return vehicle type
        return $vehicle->vehicleType->name;
    }
}

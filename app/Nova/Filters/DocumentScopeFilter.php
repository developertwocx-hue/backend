<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class DocumentScopeFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @var string
     */
    public $name = 'Document Scope';

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
        switch ($value) {
            case 'global':
                return $query->whereNull('tenant_id')->whereNull('vehicle_type_id');
            case 'vehicle_type':
                return $query->whereNull('tenant_id')->whereNotNull('vehicle_type_id');
            case 'tenant_all':
                return $query->whereNotNull('tenant_id')->whereNull('vehicle_type_id');
            case 'tenant_specific':
                return $query->whereNotNull('tenant_id')->whereNotNull('vehicle_type_id');
            default:
                return $query;
        }
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return [
            'Global' => 'global',
            'Vehicle-Type Specific' => 'vehicle_type',
            'Tenant Custom (All Types)' => 'tenant_all',
            'Tenant Custom (Type-Specific)' => 'tenant_specific',
        ];
    }
}

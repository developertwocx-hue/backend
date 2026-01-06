<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\NovaRequest;

class VehicleComplianceRequirement extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\VehicleComplianceRequirement>
     */
    public static $model = \App\Models\VehicleComplianceRequirement::class;

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
        return $this->complianceType->name ?? 'Requirement #' . $this->id;
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
     * Indicates if the resource should be displayed in the sidebar.
     *
     * @var bool
     */
    public static $displayInNavigation = false;

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Tenant', 'tenant', Tenant::class)
                ->sortable()
                ->readonly(),

            BelongsTo::make('Vehicle', 'vehicle', Vehicle::class)
                ->sortable()
                ->required(),

            BelongsTo::make('Compliance Type', 'complianceType', ComplianceType::class)
                ->sortable()
                ->required(),

            Badge::make('Current Status', 'current_status')
                ->map([
                    'compliant' => 'success',
                    'at_risk' => 'warning',
                    'expired' => 'danger',
                    'pending' => 'info',
                ])
                ->displayUsing(fn() => ucfirst($this->getCurrentStatus()))
                ->exceptOnForms(),

            Text::make('Days Until Expiry', 'days_until_expiry')
                ->onlyOnIndex()
                ->displayUsing(function() {
                    $days = $this->getDaysUntilExpiry();
                    if ($days === null) return 'No record';
                    if ($days < 0) return abs($days) . ' days overdue';
                    return $days . ' days';
                }),

            Boolean::make('Is Required', 'is_required')
                ->sortable()
                ->help('Is this compliance mandatory for this vehicle?'),

            Boolean::make('Is Overdue', function() {
                return $this->isOverdue();
            })->exceptOnForms(),

            HasMany::make('Records', 'records', ComplianceRecord::class),

            DateTime::make('Created At', 'created_at')
                ->hideFromIndex()
                ->readonly(),

            DateTime::make('Updated At', 'updated_at')
                ->hideFromIndex()
                ->readonly(),
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
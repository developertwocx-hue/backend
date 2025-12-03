<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Email;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Http\Requests\NovaRequest;

class Tenant extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Tenant>
     */
    public static $model = \App\Models\Tenant::class;

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
        'id', 'name', 'email',
    ];

    /**
     * Get the URI key for the resource.
     */
    public static function uriKey(): string
    {
        return 'tenants';
    }

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

            Text::make('Business Name', 'name')
                ->sortable()
                ->rules('required', 'max:255'),

            Email::make('Email')
                ->sortable()
                ->rules('required', 'email', 'max:254')
                ->creationRules('unique:tenants,email')
                ->updateRules('unique:tenants,email,{{resourceId}}'),

            Text::make('Phone')
                ->sortable()
                ->rules('nullable', 'max:20'),

            Text::make('Address')
                ->hideFromIndex()
                ->rules('nullable'),

            Select::make('Subscription Plan', 'subscription_plan')
                ->options([
                    'free' => 'Free',
                    'basic' => 'Basic',
                    'professional' => 'Professional',
                    'enterprise' => 'Enterprise',
                ])
                ->displayUsingLabels()
                ->sortable()
                ->rules('required'),

            DateTime::make('Subscription Ends At', 'subscription_ends_at')
                ->sortable()
                ->rules('required'),

            HasMany::make('Users'),
            HasMany::make('Vehicles'),
            HasMany::make('Vehicle Types', 'vehicleTypes', VehicleType::class),

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

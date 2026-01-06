<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;

class AustralianState extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\AustralianState>
     */
    public static $model = \App\Models\AustralianState::class;

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
        'code', 'name',
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
            ID::make()->sortable()->hide(),

            Text::make('Code')
                ->sortable()
                ->rules('required', 'max:10')
                ->help('State code (e.g., NSW, VIC, QLD)'),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255')
                ->help('Full state name'),

            Boolean::make('Has Green Sticker Requirement', 'has_green_sticker_requirement')
                ->help('Does this state require green sticker inspections?'),

            Number::make('Green Sticker Frequency (Days)', 'green_sticker_frequency_days')
                ->min(1)
                ->nullable()
                ->help('How often green sticker renewal is required'),

            Textarea::make('Notes')
                ->hideFromIndex()
                ->help('Additional notes about this state\'s requirements'),
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
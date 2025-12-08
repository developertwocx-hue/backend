<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\NovaRequest;

class DocumentType extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\DocumentType>
     */
    public static $model = \App\Models\DocumentType::class;

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
        'id', 'name', 'description',
    ];

    /**
     * Get the displayable label of the resource.
     */
    public static function label()
    {
        return 'Document Types';
    }

    /**
     * Get the displayable singular label of the resource.
     */
    public static function singularLabel()
    {
        return 'Document Type';
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

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255')
                ->help('Name of the document type (e.g., Insurance, Registration)'),

            Textarea::make('Description')
                ->rows(3)
                ->nullable()
                ->help('Optional description of what this document type is for'),

            Badge::make('Scope', function() {
                return $this->scope_type;
            })
                ->map([
                    'Global' => 'success',
                    'Vehicle-Type Specific' => 'info',
                    'Tenant Custom (All Types)' => 'warning',
                    'Tenant Custom (Type-Specific)' => 'warning',
                ])
                ->label(function($value) {
                    return $value;
                })
                ->onlyOnIndex(),

            BelongsTo::make('Vehicle Type', 'vehicleType', VehicleType::class)
                ->nullable()
                ->sortable()
                ->withoutTrashed()
                ->help('Leave empty for global scope, select a vehicle type to make it vehicle-type specific')
                ->hideFromIndex(),

            BelongsTo::make('Tenant')
                ->nullable()
                ->sortable()
                ->withoutTrashed()
                ->readonly()
                ->help('Automatically set for tenant-created types, superadmin types have this as NULL')
                ->hideWhenCreating(),

            Boolean::make('Required', 'is_required')
                ->default(false)
                ->help('Mark if this document is mandatory for vehicles'),

            Boolean::make('Active', 'is_active')
                ->default(true)
                ->help('Inactive document types won\'t appear in selection lists'),

            Number::make('Sort Order', 'sort_order')
                ->default(100)
                ->min(0)
                ->max(1000)
                ->step(10)
                ->help('Lower numbers appear first in lists')
                ->hideFromIndex(),

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

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(NovaRequest $request, $query): \Illuminate\Contracts\Database\Eloquent\Builder
    {
        // Show all document types for superadmin
        return $query;
    }

    /**
     * Determine if the current user can create new resources.
     */
    public static function authorizedToCreate(Request $request)
    {
        // Temporarily allow all authenticated users to create document types in Nova
        // TODO: Restrict to superadmin only in production
        return $request->user() !== null;
    }

    /**
     * Determine if the current user can update the given resource.
     */
    public function authorizedToUpdate(Request $request)
    {
        // Temporarily allow all authenticated users to update document types in Nova
        // TODO: Restrict to superadmin only in production
        return $request->user() !== null;
    }

    /**
     * Determine if the current user can delete the given resource.
     */
    public function authorizedToDelete(Request $request)
    {
        // Temporarily allow all authenticated users to delete document types in Nova
        // TODO: Restrict to superadmin only in production
        // Don't allow deleting if it's being used by documents
        return $request->user() !== null && $this->vehicleDocuments()->count() === 0;
    }
}

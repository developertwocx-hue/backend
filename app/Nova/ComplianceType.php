<?php

namespace App\Nova;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Http\Requests\NovaRequest;

class ComplianceType extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\ComplianceType>
     */
    public static $model = \App\Models\ComplianceType::class;

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
        'id', 'name', 'description', 'category',
    ];

    /**
     * The default ordering for the resource index.
     *
     * @var array
     */
    public static $defaultOrder = [
        'sort_order' => 'asc',
        'name' => 'asc',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Scope Type')
                ->onlyOnIndex()
                ->displayUsing(fn() => $this->scope_type),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255')
                ->help('Compliance type name (e.g., "Heavy Vehicle Inspection (NSW)")'),

            Textarea::make('Description')
                ->hideFromIndex()
                ->rules('nullable')
                ->help('Detailed description of this compliance requirement'),

            Select::make('Category')
                ->options([
                    'roadworthiness' => 'Roadworthiness',
                    'registration' => 'Registration',
                    'insurance' => 'Insurance',
                    'inspection' => 'Inspection',
                    'other' => 'Other',
                ])
                ->displayUsingLabels()
                ->sortable()
                ->rules('required')
                ->help('Category of compliance'),

            BelongsTo::make('Vehicle Type', 'vehicleType', VehicleType::class)
                ->nullable()
                ->sortable()
                ->help('Leave empty for all vehicle types, or select specific type'),

            Select::make('State', 'state_code')
                ->options([
                    'NSW' => 'New South Wales',
                    'VIC' => 'Victoria',
                    'QLD' => 'Queensland',
                    'WA' => 'Western Australia',
                    'SA' => 'South Australia',
                    'TAS' => 'Tasmania',
                    'ACT' => 'Australian Capital Territory',
                    'NT' => 'Northern Territory',
                ])
                ->nullable()
                ->sortable()
                ->help('Leave empty for all states, or select specific state')
                ->canSee(function ($request) {
                    // Only superadmins can create state-specific types
                    return $request->user()->role === 'superadmin';
                })
                ->readonly(function ($request) {
                    // Tenants cannot modify state field
                    return $request->user()->role !== 'superadmin';
                }),

            BelongsTo::make('Tenant', 'tenant', Tenant::class)
                ->nullable()
                ->sortable()
                ->help('Leave empty for global types. Superadmin only can create global types.')
                ->displayUsing(fn($tenant) => $tenant ? $tenant->name : 'Global (All Tenants)')
                ->canSee(function ($request) {
                    // Only superadmins can see/modify tenant field
                    return $request->user()->role === 'superadmin';
                })
                ->readonly(function ($request) {
                    // Tenants cannot modify tenant field
                    return $request->user()->role !== 'superadmin';
                }),

            Number::make('Renewal Frequency (Days)', 'renewal_frequency_days')
                ->min(1)
                ->nullable()
                ->help('How often this compliance needs renewal (e.g., 365 for annual)'),

            Boolean::make('Requires Document', 'requires_document')
                ->help('Does this compliance require document evidence?')
                ->default(true),

            Boolean::make('Is Required', 'is_required')
                ->help('Is this compliance mandatory for vehicles?')
                ->default(true),

            Boolean::make('Active', 'is_active')
                ->sortable()
                ->default(true),

            KeyValue::make('Alert Thresholds', 'alert_thresholds')
                ->rules('nullable', 'json')
                ->help('Days before expiry to send alerts (JSON array). Example: [30, 14, 7, 0]')
                ->hideFromIndex()
                ->nullable(),

            Number::make('Sort Order', 'sort_order')
                ->min(0)
                ->default(0)
                ->help('Display order (lower numbers appear first)'),

            HasMany::make('Requirements', 'requirements', VehicleComplianceRequirement::class),

            HasMany::make('Records', 'records', ComplianceRecord::class),
        ];
    }

    /**
     * Handle resource creation
     */
    public static function afterCreate(NovaRequest $request, $model)
    {
        // Auto-fill tenant_id for non-superadmins (tenant custom types)
        if ($request->user()->role !== 'superadmin') {
            $model->tenant_id = $request->user()->tenant_id;
            $model->state_code = null; // Tenants cannot create state-specific types
            $model->save();
        }
    }

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        // Superadmins see all types, tenants see global + their custom types
        if ($request->user()->role === 'superadmin') {
            return $query;
        }

        // Show global types (tenant_id is null) OR tenant's custom types
        $tenantId = $request->user()->tenant_id;
        return $query->where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id')
              ->orWhere('tenant_id', '=', $tenantId);
        });
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
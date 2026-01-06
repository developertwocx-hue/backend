<?php

namespace App\Nova;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Http\Requests\NovaRequest;

class ComplianceRecord extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\ComplianceRecord>
     */
    public static $model = \App\Models\ComplianceRecord::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
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

            BelongsTo::make('Tenant', 'tenant', Tenant::class)
                ->exceptOnForms()
                ->sortable(),

            BelongsTo::make('Vehicle', 'vehicle', Vehicle::class)
                ->sortable()
                ->withoutTrashed()
                ->rules('required', 'exists:vehicles,id')
                ->help('Select the vehicle for this compliance record')
                ->displayUsing(function ($vehicle) {
                    if (!$vehicle) return null;
                    return ($vehicle->vehicleType->name ?? 'Unknown') . ' #' . $vehicle->id;
                })
                ->relatableQueryUsing(function ($request, $query) {
                    // Superadmins see all vehicles, regular users see only their tenant's vehicles
                    if ($request->user()->role === 'superadmin') {
                        return $query;
                    }
                    return $query->where('tenant_id', $request->user()->tenant_id);
                }),

            BelongsTo::make('Compliance Type', 'complianceType', ComplianceType::class)
                ->sortable()
                ->searchable()
                ->rules('required', 'exists:compliance_types,id')
                ->help('Select the type of compliance being submitted')
                ->relatableQueryUsing(function ($request, $query) {
                    // Superadmins see all active types
                    if ($request->user()->role === 'superadmin') {
                        return $query->where('is_active', true);
                    }

                    // Show global types + tenant's custom types
                    $tenantId = $request->user()->tenant_id;
                    return $query->where('is_active', true)
                        ->where(function ($q) use ($tenantId) {
                            $q->whereNull('tenant_id') // Global types
                              ->orWhere('tenant_id', $tenantId); // Tenant custom
                        });
                }),

            Badge::make('Status')
                ->map([
                    'compliant' => 'success',
                    'at_risk' => 'warning',
                    'expired' => 'danger',
                    'pending' => 'info',
                ])
                ->displayUsing(fn() => ucfirst($this->status))
                ->sortable(),

            Text::make('Days Until Expiry', 'days_until_expiry')
                ->onlyOnIndex()
                ->displayUsing(function() {
                    $days = $this->days_until_expiry;
                    if ($days === null) return 'N/A';
                    if ($days < 0) return abs($days) . ' days overdue';
                    return $days . ' days';
                }),

            Date::make('Issue Date', 'issue_date')
                ->sortable()
                ->required(),

            Date::make('Expiry Date', 'expiry_date')
                ->sortable()
                ->required(),

            Textarea::make('Notes')
                ->hideFromIndex()
                ->nullable(),

            File::make('Upload Document', 'compliance_document')
                ->disk('public')
                ->path('vehicle-documents')
                ->acceptedTypes('.pdf,.jpg,.jpeg,.png,.doc,.docx')
                ->help('Upload compliance document (PDF, JPG, PNG, DOC, DOCX - Max 100MB)')
                ->hideFromIndex()
                ->hideFromDetail()
                ->onlyOnForms()
                ->deletable(false)
                ->nullable()
                ->rules('nullable', 'file', 'max:102400')
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    // Do nothing - file is handled in afterCreate/afterUpdate
                    // Return null to prevent Nova from trying to save this field
                    return null;
                }),

            Boolean::make('Is Current', 'is_current')
                ->exceptOnForms()
                ->sortable()
                ->help('Only one record can be current per requirement'),

            BelongsTo::make('Submitted By', 'submittedBy', User::class)
                ->exceptOnForms()
                ->sortable(),

            BelongsTo::make('Approved By', 'approvedBy', User::class)
                ->exceptOnForms()
                ->nullable()
                ->sortable(),

            DateTime::make('Approved At', 'approved_at')
                ->nullable()
                ->readonly(),

            BelongsToMany::make('Documents', 'documents', VehicleDocument::class),

            DateTime::make('Created At', 'created_at')
                ->hideFromIndex()
                ->readonly(),

            DateTime::make('Updated At', 'updated_at')
                ->hideFromIndex()
                ->readonly(),
        ];
    }

    /**
     * Handle resource creation
     */
    public static function afterCreate(NovaRequest $request, $model)
    {
        // Auto-fill tenant_id and submitted_by from authenticated user
        $user = $request->user();
        $model->tenant_id = $user->tenant_id;
        $model->submitted_by = $user->id;
        $model->save();

        // Handle document upload
        static::handleDocumentUpload($request, $model);
    }

    /**
     * Handle resource update
     */
    public static function afterUpdate(NovaRequest $request, $model)
    {
        // Handle document upload on update
        static::handleDocumentUpload($request, $model);
    }

    /**
     * Handle document upload and attachment
     */
    protected static function handleDocumentUpload($request, $model)
    {
        if ($request->hasFile('compliance_document')) {
            $file = $request->file('compliance_document');
            $filePath = $file->store('vehicle-documents', 'public');

            // Create vehicle document record
            $document = \App\Models\VehicleDocument::create([
                'tenant_id' => $model->tenant_id,
                'vehicle_id' => $model->vehicle_id,
                'document_type_id' => null,
                'document_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);

            // Attach document to compliance record
            \Illuminate\Support\Facades\DB::table('compliance_record_documents')->insert([
                'compliance_record_id' => $model->id,
                'vehicle_document_id' => $document->id,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        // Superadmins see all records, regular users see only their tenant's records
        if ($request->user()->role === 'superadmin') {
            return $query;
        }

        // Only show compliance records for current tenant's vehicles
        return $query->where('tenant_id', $request->user()->tenant_id);
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
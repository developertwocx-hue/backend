<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Features;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Customize Nova branding
        Nova::style('custom', __DIR__.'/../../public/css/nova-custom.css');

        // Set Nova initial path
        Nova::initialPath('/dashboards/main');

        // Register resources explicitly
        Nova::resources([
            \App\Nova\Tenant::class,
            \App\Nova\User::class,
            \App\Nova\VehicleType::class,
            \App\Nova\VehicleTypeField::class,
            \App\Nova\Vehicle::class,
            \App\Nova\DocumentType::class,
            \App\Nova\VehicleDocument::class,
        ]);

        // Configure sidebar menu
        Nova::mainMenu(function ($request) {
            return [
                \Laravel\Nova\Menu\MenuSection::dashboard(\App\Nova\Dashboards\Main::class)->icon('chart-bar'),

                \Laravel\Nova\Menu\MenuSection::make('Tenants')
                    ->path('/resources/tenants')
                    ->icon('building-office-2'),

                \Laravel\Nova\Menu\MenuSection::make('Users')
                    ->path('/resources/users')
                    ->icon('users'),

                \Laravel\Nova\Menu\MenuSection::make('Vehicle Types')
                    ->path('/resources/vehicle-types')
                    ->icon('square-3-stack-3d'),

                \Laravel\Nova\Menu\MenuSection::make('Vehicle Type Fields')
                    ->path('/resources/vehicle-type-fields')
                    ->icon('adjustments-horizontal'),

                \Laravel\Nova\Menu\MenuSection::make('Vehicles')
                    ->path('/resources/vehicles')
                    ->icon('truck'),

                \Laravel\Nova\Menu\MenuSection::make('Document Types')
                    ->path('/resources/document-types')
                    ->icon('folder-open'),

                \Laravel\Nova\Menu\MenuSection::make('Vehicle Documents')
                    ->path('/resources/vehicle-documents')
                    ->icon('document-text'),
            ];
        });

        Nova::serving(function () {
            Nova::provideToScript([
                'appName' => 'Cranelift SaaS',
            ]);
        });
    }

    /**
     * Register the configurations for Laravel Fortify.
     */
    protected function fortify(): void
    {
        Nova::fortify()
            ->features([
                Features::updatePasswords(),
                // Features::emailVerification(),
                // Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
            ])
            ->register();
    }

    /**
     * Register the Nova routes.
     */
    protected function routes(): void
    {
        Nova::routes()
            ->withAuthenticationRoutes()
            ->withPasswordResetRoutes()
            ->withoutEmailVerificationRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', function (?User $user) {
            // Only allow users with 'superadmin' role to access Nova
            return $user !== null && $user->role === 'superadmin';
        });
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Dashboard>
     */
    protected function dashboards(): array
    {
        return [
            new \App\Nova\Dashboards\Main,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Tool>
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * Get the resources that should be listed in the Nova sidebar.
     *
     * @return array<int, class-string<\Laravel\Nova\Resource>>
     */
    public function resources(): array
    {
        return [
            \App\Nova\Tenant::class,
            \App\Nova\User::class,
            \App\Nova\VehicleType::class,
            \App\Nova\VehicleTypeField::class,
            \App\Nova\Vehicle::class,
            \App\Nova\DocumentType::class,
            \App\Nova\VehicleDocument::class,
        ];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        //
    }
}

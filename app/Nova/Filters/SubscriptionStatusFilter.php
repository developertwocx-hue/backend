<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class SubscriptionStatusFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @var string
     */
    public $name = 'Subscription Status';

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
            case 'active':
                return $query->where('subscription_ends_at', '>', now());
            case 'expired':
                return $query->where('subscription_ends_at', '<=', now());
            case 'expiring_soon':
                return $query->whereBetween('subscription_ends_at', [now(), now()->addDays(30)]);
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
            'Active' => 'active',
            'Expired' => 'expired',
            'Expiring Soon (30 days)' => 'expiring_soon',
        ];
    }
}

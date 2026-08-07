<?php

namespace App\Services\Admin;

use App\Http\Requests\Admin\OrganizationIndexRequest;
use App\Models\Team;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Builds the platform admin's organization index query: search, filters,
 * sorting, and real pagination — replacing the previous unbounded
 * `latest()->limit(500)->get()` that was filtered client-side.
 */
class AdminOrganizationQuery
{
    public function paginate(OrganizationIndexRequest $request): LengthAwarePaginator
    {
        $filters = $request->validated();

        return Team::query()
            ->withCount('workspaces')
            ->with('owner:id,name,email')
            ->when($filters['search'] ?? null, fn (Builder $q, $search) => $q->where(
                fn (Builder $q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('custom_domain', 'like', "%{$search}%")
                    ->orWhere('stripe_id', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn (Builder $q) => $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")),
            ))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $q) => $q->whereNull('suspended_at'))
            ->when(($filters['status'] ?? null) === 'suspended', fn (Builder $q) => $q->whereNotNull('suspended_at'))
            ->when($filters['plan'] ?? null, fn (Builder $q, $plan) => $this->whereEffectivePlan($q, $plan))
            ->when($filters['base_plan'] ?? null, fn (Builder $q, $plan) => $q->where('plan', $plan))
            ->when($filters['subscription_status'] ?? null, fn (Builder $q, $status) => $q->whereHas(
                'subscriptions', fn (Builder $q) => $q->where('stripe_status', $status),
            ))
            ->when($filters['created_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['created_to'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderBy($request->sort(), $request->direction())
            ->paginate($request->perPage())
            ->withQueryString();
    }

    /**
     * An organization's *effective* plan matches `$plan` when either its
     * active override says so, or (absent an active override) its base plan
     * does — the same precedence `PlanService::planKey()` resolves in PHP,
     * expressed as SQL so it can be searched/filtered without loading every
     * row.
     */
    protected function whereEffectivePlan(Builder $query, string $plan): Builder
    {
        $now = Carbon::now();

        return $query->where(function (Builder $q) use ($plan, $now) {
            $q->where(function (Builder $q) use ($plan, $now) {
                $q->where('plan_override', $plan)
                    ->where(function (Builder $q) use ($now) {
                        $q->whereNull('plan_override_expires_at')
                            ->orWhere('plan_override_expires_at', '>', $now);
                    });
            })->orWhere(function (Builder $q) use ($plan, $now) {
                $q->where('plan', $plan)
                    ->where(function (Builder $q) use ($now) {
                        $q->whereNull('plan_override')
                            ->orWhere(function (Builder $q) use ($now) {
                                $q->whereNotNull('plan_override_expires_at')
                                    ->where('plan_override_expires_at', '<=', $now);
                            });
                    });
            });
        });
    }
}

<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

/**
 * In Scheduleistic a Jetstream "team" is an Organization (an agency / tenant /
 * white-label account). Each organization owns many client Workspaces, carries
 * a plan + subscription (Cashier billable), and its own white-label branding.
 */
class Team extends JetstreamTeam
{
    use Billable;

    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_team',
        'plan',
        'entitlements',
        'timezone',
        'onboarded_at',
        'branding',
        'custom_domain',
        'custom_domain_verified_at',
        'custom_domain_token',
        'suspended_at',
        'plan_override',
        'plan_override_expires_at',
        'plan_override_reason',
        'plan_override_set_by',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
            'branding' => 'array',
            'entitlements' => 'array',
            'onboarded_at' => 'datetime',
            'custom_domain_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'plan_override_expires_at' => 'datetime',
        ];
    }

    /** Client workspaces owned by this organization. */
    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    /** The platform admin who granted the current plan override, if any. */
    public function planOverrideSetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'plan_override_set_by');
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Resolve the plan key this organization is entitled to from its active
     * Stripe subscription (falls back to free / its current value).
     */
    public function planFromSubscription(): string
    {
        $subscription = $this->subscription('default');

        if (! $subscription || ! $subscription->valid()) {
            return 'free';
        }

        foreach (config('plans') as $key => $plan) {
            if (! empty($plan['price_id']) && $plan['price_id'] === $subscription->stripe_price) {
                return $key;
            }
        }

        return $this->plan; // unknown price — leave entitlement unchanged
    }

    /** Reconcile the stored plan column with the live subscription. */
    public function syncPlanFromSubscription(): void
    {
        $plan = $this->planFromSubscription();

        if ($this->plan !== $plan) {
            $this->forceFill(['plan' => $plan])->save();
        }
    }

    public function customDomainVerified(): bool
    {
        return $this->custom_domain_verified_at !== null;
    }

    /**
     * Effective branding for this tenant, merged over the platform defaults.
     *
     * @return array<string, mixed>
     */
    public function brandingConfig(): array
    {
        $defaults = [
            'name' => config('scheduleistic.name'),
            'tagline' => config('scheduleistic.tagline'),
            'powered_by' => config('scheduleistic.powered_by'),
            'primary' => config('scheduleistic.colors.primary'),
            'secondary' => config('scheduleistic.colors.secondary'),
            'logo' => config('scheduleistic.logo'),
        ];

        return array_merge($defaults, array_filter($this->branding ?? []));
    }
}

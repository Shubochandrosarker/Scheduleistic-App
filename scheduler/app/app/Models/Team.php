<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

/**
 * In SOCIALISTIC a Jetstream "team" is an Organization (an agency / tenant /
 * white-label account). Each organization owns many client Workspaces, carries
 * a plan + subscription (Cashier billable), and its own white-label branding.
 */
class Team extends JetstreamTeam
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;
    use Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'personal_team',
        'plan',
        'branding',
        'custom_domain',
        'custom_domain_verified_at',
        'custom_domain_token',
        'suspended_at',
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
            'personal_team'             => 'boolean',
            'branding'                  => 'array',
            'custom_domain_verified_at' => 'datetime',
            'suspended_at'              => 'datetime',
        ];
    }

    /** Client workspaces owned by this organization. */
    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
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
            'name'       => config('socialistic.name'),
            'tagline'    => config('socialistic.tagline'),
            'powered_by' => config('socialistic.powered_by'),
            'primary'    => config('socialistic.colors.primary'),
            'secondary'  => config('socialistic.colors.secondary'),
            'logo'       => config('socialistic.logo'),
        ];

        return array_merge($defaults, array_filter($this->branding ?? []));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user, per-event delivery preferences.
 *
 * `EVENTS` is the catalogue the preferences screen renders and the notifier
 * validates against — an event not listed here cannot be subscribed to, so a
 * typo in a dispatch call fails loudly instead of silently notifying nobody.
 */
class NotificationPreference extends Model
{
    /** event key => [label, description, default channels] */
    public const EVENTS = [
        'approval_requested' => ['Approval requested', 'Someone asked you to review a post.', ['in_app' => true, 'email' => true]],
        'approval_completed' => ['Approval completed', 'A post you submitted was approved.', ['in_app' => true, 'email' => true]],
        'changes_requested'  => ['Changes requested', 'A reviewer sent a post back.', ['in_app' => true, 'email' => true]],
        'mentioned'          => ['Mentioned in a comment', 'Someone @mentioned you.', ['in_app' => true, 'email' => true]],
        'post_assigned'      => ['Post assigned to you', 'A post was assigned to you.', ['in_app' => true, 'email' => false]],
        'publish_failed'     => ['Publish failed', 'A scheduled post could not be published.', ['in_app' => true, 'email' => true]],
        'publish_partial'    => ['Partial publish', 'A post published to some networks but not all.', ['in_app' => true, 'email' => true]],
        'channel_expiring'   => ['Connection expiring', 'A social profile needs reconnecting soon.', ['in_app' => true, 'email' => true]],
        'report_ready'       => ['Report ready', 'A scheduled report finished generating.', ['in_app' => true, 'email' => true]],
        'subscription_issue' => ['Billing problem', 'A payment or subscription needs attention.', ['in_app' => true, 'email' => true]],
        'usage_threshold'    => ['Usage threshold', 'You are approaching a plan limit.', ['in_app' => true, 'email' => false]],
        'domain_verified'    => ['Domain verification', 'A custom domain changed verification state.', ['in_app' => true, 'email' => true]],
        'import_completed'   => ['Import completed', 'A bulk import finished.', ['in_app' => true, 'email' => false]],
        'feed_error'         => ['Feed error', 'An RSS or WordPress feed failed to poll.', ['in_app' => true, 'email' => false]],
    ];

    public const DIGESTS = ['immediate', 'daily', 'weekly', 'off'];

    protected $fillable = ['user_id', 'event', 'in_app', 'email', 'push', 'digest'];

    protected function casts(): array
    {
        return ['in_app' => 'boolean', 'email' => 'boolean', 'push' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The stored preference for an event, or the documented default. */
    public static function resolve(User $user, string $event): array
    {
        $stored = $user->notificationPreferences->firstWhere('event', $event);

        if ($stored) {
            return [
                'in_app' => $stored->in_app,
                'email'  => $stored->email,
                'push'   => $stored->push,
                'digest' => $stored->digest,
            ];
        }

        $defaults = self::EVENTS[$event][2] ?? [];

        return [
            'in_app' => $defaults['in_app'] ?? true,
            'email'  => $defaults['email'] ?? false,
            'push'   => false,
            'digest' => 'immediate',
        ];
    }
}

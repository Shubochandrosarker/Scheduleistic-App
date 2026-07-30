<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\SsrfGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Delivers one webhook, signed.
 *
 * Receivers verify with:
 *
 *     signature == hmac_sha256(secret, "{timestamp}.{raw_body}")
 *
 * The timestamp is in the signed string so a captured payload cannot be
 * replayed later against the same signature, and it is sent in its own header
 * so the receiver can enforce a freshness window.
 *
 * The destination URL is re-checked against SsrfGuard on every attempt, not
 * just at creation: DNS can be re-pointed at a private address after the
 * endpoint is saved, and the worker is inside the network perimeter.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** Exponential backoff: ~10s, 1m, 5m, 30m. */
    public array $backoff = [10, 60, 300, 1800];

    public function __construct(public int $deliveryId) {}

    public function handle(SsrfGuard $guard): void
    {
        $delivery = WebhookDelivery::with('endpoint')->find($this->deliveryId);

        if (! $delivery || $delivery->status === WebhookDelivery::STATUS_DELIVERED) {
            return; // already done, or the endpoint was deleted
        }

        $endpoint = $delivery->endpoint;

        if (! $endpoint || ! $endpoint->is_active || $endpoint->disabled_at) {
            $this->markFailed($delivery, null, 'Endpoint is disabled.');

            return;
        }

        if (! $guard->isFetchable($endpoint->url)) {
            // Not retryable — the URL itself is the problem.
            $this->markFailed($delivery, null, 'Destination resolves to a blocked address.');
            $this->disable($endpoint, 'Destination resolves to a blocked address.');

            return;
        }

        $body      = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES);
        $timestamp = now()->getTimestamp();
        $started   = microtime(true);

        $delivery->increment('attempts');

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type'                  => 'application/json',
                    'User-Agent'                    => 'Scheduleistic-Webhooks/2.0',
                    'X-Scheduleistic-Event'         => $delivery->event,
                    'X-Scheduleistic-Delivery'      => $delivery->event_id,
                    'X-Scheduleistic-Timestamp'     => (string) $timestamp,
                    'X-Scheduleistic-Signature'     => 'sha256='.$endpoint->sign($body, $timestamp),
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);
        } catch (\Throwable $e) {
            $this->markFailed($delivery, null, $e->getMessage(), (int) ((microtime(true) - $started) * 1000));
            $this->registerFailure($endpoint);

            throw $e; // retry with backoff
        }

        $duration = (int) ((microtime(true) - $started) * 1000);

        if ($response->successful()) {
            $delivery->update([
                'status'          => WebhookDelivery::STATUS_DELIVERED,
                'response_status' => $response->status(),
                'response_body'   => mb_substr($response->body(), 0, 2000),
                'duration_ms'     => $duration,
                'delivered_at'    => now(),
            ]);

            // A success clears the failure streak, so an endpoint that recovers
            // is not disabled by history alone.
            if ($endpoint->consecutive_failures > 0) {
                $endpoint->forceFill(['consecutive_failures' => 0])->save();
            }

            return;
        }

        $this->markFailed($delivery, $response->status(), mb_substr($response->body(), 0, 2000), $duration);
        $this->registerFailure($endpoint);

        // 4xx other than 408/429 will not succeed on retry.
        if ($response->status() >= 400 && $response->status() < 500
            && ! in_array($response->status(), [408, 429], true)) {
            $this->fail(new \RuntimeException("Webhook rejected with {$response->status()}."));

            return;
        }

        throw new \RuntimeException("Webhook delivery failed with {$response->status()}.");
    }

    protected function markFailed(WebhookDelivery $delivery, ?int $status, string $body, ?int $duration = null): void
    {
        $delivery->update([
            'status'          => WebhookDelivery::STATUS_FAILED,
            'response_status' => $status,
            'response_body'   => mb_substr($body, 0, 2000),
            'duration_ms'     => $duration,
        ]);
    }

    protected function registerFailure(WebhookEndpoint $endpoint): void
    {
        $endpoint->increment('consecutive_failures');

        if ($endpoint->fresh()->consecutive_failures >= WebhookEndpoint::FAILURE_THRESHOLD) {
            $this->disable($endpoint, 'Disabled automatically after '.WebhookEndpoint::FAILURE_THRESHOLD.' consecutive failures.');
        }
    }

    protected function disable(WebhookEndpoint $endpoint, string $reason): void
    {
        $endpoint->forceFill([
            'is_active'       => false,
            'disabled_at'     => now(),
            'disabled_reason' => $reason,
        ])->save();
    }
}

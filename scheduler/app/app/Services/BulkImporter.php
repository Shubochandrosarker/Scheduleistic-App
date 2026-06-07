<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Bulk-creates posts from CSV rows. Each row: content, optional scheduled_at,
 * and optional pipe-separated provider keys (defaults to all channels).
 */
class BulkImporter
{
    public function __construct(private readonly PostComposer $composer) {}

    /**
     * @param  iterable<int, array<string, string>>  $rows  associative rows (header => value)
     * @return array{created:int, skipped:int, posts:array<int, int>}
     */
    public function import(Workspace $workspace, iterable $rows, ?int $authorId = null): array
    {
        $channelsByProvider = $workspace->channels()->get()->keyBy('provider');
        $allChannelIds      = $channelsByProvider->pluck('id')->all();

        $created = 0;
        $skipped = 0;
        $postIds = [];

        foreach ($rows as $row) {
            $content = trim($row['content'] ?? '');

            if ($content === '' || empty($allChannelIds)) {
                $skipped++;
                continue;
            }

            $channelIds = $this->resolveChannelIds($row, $channelsByProvider, $allChannelIds);

            if (empty($channelIds)) {
                $skipped++;
                continue;
            }

            $scheduledAt = ! empty($row['scheduled_at'])
                ? Carbon::parse($row['scheduled_at'])
                : null;

            $post = $this->composer->schedule(
                workspace: $workspace,
                content: $content,
                channelIds: $channelIds,
                scheduledAt: $scheduledAt,
                authorId: $authorId,
                useQueue: $scheduledAt === null,
            );

            $created++;
            $postIds[] = $post->id;
        }

        return ['created' => $created, 'skipped' => $skipped, 'posts' => $postIds];
    }

    /**
     * Parse raw CSV text into associative rows keyed by the header line.
     *
     * @return array<int, array<string, string>>
     */
    public function parseCsv(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];

        if (count($lines) < 2) {
            return [];
        }

        $headers = array_map('trim', str_getcsv(array_shift($lines)));
        $rows    = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line);
            $rows[] = array_combine(
                $headers,
                array_pad(array_slice($values, 0, count($headers)), count($headers), ''),
            );
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @param  \Illuminate\Support\Collection<string, \App\Models\Channel>  $byProvider
     * @param  array<int, int>  $allIds
     * @return array<int, int>
     */
    protected function resolveChannelIds(array $row, $byProvider, array $allIds): array
    {
        $providers = trim($row['providers'] ?? '');

        if ($providers === '') {
            return $allIds;
        }

        return collect(explode('|', $providers))
            ->map(fn ($p) => $byProvider->get(trim($p))?->id)
            ->filter()
            ->values()
            ->all();
    }
}

<?php

namespace App\Services;

use App\Models\Feed;
use App\Models\Post;
use Illuminate\Support\Facades\Http;

/**
 * Polls an RSS/Atom feed (e.g. a WordPress blog) and turns new items into
 * draft posts for the feed's workspace — the article-to-social pipeline.
 */
class RssIngestService
{
    public function __construct(private readonly PostComposer $composer) {}

    /** @return int number of new draft posts created */
    public function ingest(Feed $feed, ?string $rawXml = null): int
    {
        $xml = $rawXml ?? $this->fetch($feed->url);

        if ($xml === null) {
            return 0;
        }

        $items   = $this->parse($xml);
        $seen    = $feed->seen_guids ?? [];
        $channels = $feed->workspace->channels()->pluck('id')->all();
        $created = 0;

        foreach ($items as $item) {
            $guid = $item['guid'];

            if (in_array($guid, $seen, true)) {
                continue;
            }
            $seen[] = $guid;

            if (empty($channels)) {
                continue;
            }

            $content = trim($item['title'].' '.$item['link']);

            $this->composer->schedule(
                workspace: $feed->workspace,
                content: $content,
                channelIds: $channels,
            );

            $created++;
        }

        $feed->update([
            'seen_guids'     => array_slice($seen, -500),
            'last_polled_at' => now(),
        ]);

        return $created;
    }

    private function fetch(string $url): ?string
    {
        $response = Http::get($url);

        return $response->successful() ? $response->body() : null;
    }

    /**
     * @return array<int, array{guid:string, title:string, link:string}>
     */
    public function parse(string $xml): array
    {
        $items = [];

        $sxml = @simplexml_load_string($xml);
        if ($sxml === false) {
            return [];
        }

        // RSS 2.0
        foreach ($sxml->channel->item ?? [] as $item) {
            $items[] = [
                'guid'  => (string) ($item->guid ?: $item->link),
                'title' => trim((string) $item->title),
                'link'  => trim((string) $item->link),
            ];
        }

        // Atom
        if (empty($items) && isset($sxml->entry)) {
            foreach ($sxml->entry as $entry) {
                $link = '';
                foreach ($entry->link as $l) {
                    $link = (string) $l['href'];
                }
                $items[] = [
                    'guid'  => (string) ($entry->id ?: $link),
                    'title' => trim((string) $entry->title),
                    'link'  => $link,
                ];
            }
        }

        return $items;
    }
}

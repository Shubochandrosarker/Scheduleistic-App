<?php

namespace App\Social\Providers\Concerns;

use App\Models\Channel;

/**
 * Shared "switch to a different previously-listed account" logic for
 * providers that resolve a page/location/board/organization at connect
 * time and store every option in `meta['available_accounts']`. A pure
 * local update: everything a switch needs was already fetched when the
 * channel was connected, so this never needs a second credential.
 */
trait SwitchesAvailableAccount
{
    /**
     * @param  string  $metaKey  the meta key naming the active resource (e.g. 'location_name', 'board_id')
     */
    protected function applyAccountSelection(Channel $channel, string $accountId, string $metaKey): void
    {
        $chosen = collect($channel->meta['available_accounts'] ?? [])->firstWhere('id', $accountId);

        if (! $chosen) {
            return; // not one of the accounts this channel was ever offered — ignore rather than corrupt state.
        }

        $channel->forceFill([
            'name' => $chosen['name'],
            'meta' => array_merge($channel->meta ?? [], [$metaKey => $chosen['id']]),
        ])->save();
    }
}

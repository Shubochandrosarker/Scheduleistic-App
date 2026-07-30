<?php

namespace App\Support;

use App\Models\Team;

/**
 * Resolves customer-facing labels for internal model concepts.
 *
 * Model and table names stay engineering-shaped (`Workspace`, `Channel`,
 * `PostTarget`); this class is the only place that knows what a customer
 * calls them. See config/terminology.php.
 */
class Terminology
{
    /** @var array<string, array{one:string, many:string}>|null */
    protected ?array $labels = null;

    /**
     * @return array<string, array{one:string, many:string}>
     */
    public function all(?Team $team = null): array
    {
        if ($this->labels !== null && $team === null) {
            return $this->labels;
        }

        /** @var array<string, mixed> $config */
        $config = config('terminology', []);

        $labels = [];

        foreach ($config as $key => $value) {
            if ($key === 'workspace_modes' || ! is_array($value)) {
                continue;
            }

            $labels[$key] = [
                'one'  => (string) ($value['one'] ?? $key),
                'many' => (string) ($value['many'] ?? $key),
            ];
        }

        // Agencies say "client", in-house teams say "brand".
        $mode = $this->workspaceMode($team);
        $modes = config('terminology.workspace_modes', []);

        if (isset($modes[$mode])) {
            $labels['workspace'] = [
                'one'  => (string) $modes[$mode]['one'],
                'many' => (string) $modes[$mode]['many'],
            ];
        }

        if ($team === null) {
            $this->labels = $labels;
        }

        return $labels;
    }

    /** The singular customer-facing label for a concept key. */
    public function one(string $key, ?Team $team = null): string
    {
        return $this->all($team)[$key]['one'] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /** The plural customer-facing label for a concept key. */
    public function many(string $key, ?Team $team = null): string
    {
        return $this->all($team)[$key]['many'] ?? ucfirst(str_replace('_', ' ', $key)).'s';
    }

    /** Lower-cased singular, for use mid-sentence ("choose a brand"). */
    public function lower(string $key, ?Team $team = null): string
    {
        return mb_strtolower($this->one($key, $team));
    }

    /** 'brand' or 'client' — how this organization refers to its workspaces. */
    public function workspaceMode(?Team $team): string
    {
        $mode = $team?->branding['workspace_mode'] ?? null;

        return in_array($mode, ['brand', 'client'], true) ? $mode : 'brand';
    }
}

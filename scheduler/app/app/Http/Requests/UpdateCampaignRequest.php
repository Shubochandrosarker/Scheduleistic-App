<?php

namespace App\Http\Requests;

use App\Models\Campaign;

/**
 * Same shape as creating, minus the workspace: a campaign never moves between
 * workspaces, because its posts, tags and reporting are all scoped to one.
 */
class UpdateCampaignRequest extends StoreCampaignRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('campaign'));
    }

    public function rules(): array
    {
        return array_diff_key(parent::rules(), ['workspace_id' => null]);
    }

    protected function resolvedWorkspaceId(): int
    {
        /** @var Campaign $campaign */
        $campaign = $this->route('campaign');

        return (int) $campaign->workspace_id;
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Idea;

class UpdateIdeaRequest extends StoreIdeaRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('idea'));
    }

    public function rules(): array
    {
        return array_merge(
            array_diff_key(parent::rules(), ['workspace_id' => null]),
            ['position' => ['nullable', 'integer', 'min:0']],
        );
    }

    protected function resolvedWorkspaceId(): int
    {
        /** @var Idea $idea */
        $idea = $this->route('idea');

        return (int) $idea->workspace_id;
    }
}

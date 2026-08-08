<?php

namespace App\Http\Requests;

use App\Models\PostTarget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validated filters for the post/schedule history page: a permanent,
 * unbounded-growth log of every publish attempt, paginated server-side —
 * distinct from the planner's date-windowed `limit(500)`, which is meant
 * for "what's coming up," not "what happened."
 */
class PostHistoryFilterRequest extends FormRequest
{
    public const SORTABLE = ['scheduled_at', 'published_at', 'created_at'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $visible = $this->user()->visibleWorkspaceIds()->all();

        return [
            'workspace_ids' => ['nullable', 'array', 'max:50'],
            'workspace_ids.*' => [Rule::in($visible)],
            'providers' => ['nullable', 'array', 'max:20'],
            'providers.*' => ['string', 'max:40'],
            'statuses' => ['nullable', 'array', 'max:10'],
            'statuses.*' => [Rule::in([
                PostTarget::STATUS_PENDING,
                PostTarget::STATUS_PUBLISHING,
                PostTarget::STATUS_PUBLISHED,
                PostTarget::STATUS_FAILED,
            ])],
            'campaign_ids' => ['nullable', 'array', 'max:50'],
            'campaign_ids.*' => ['integer', Rule::exists('campaigns', 'id')->whereIn('workspace_id', $visible)],
            'search' => ['nullable', 'string', 'max:200'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }

    public function sort(): string
    {
        return $this->validated('sort') ?? 'scheduled_at';
    }

    public function direction(): string
    {
        return $this->validated('direction') ?? 'desc';
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 25);
    }
}

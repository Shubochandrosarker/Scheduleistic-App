<?php

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The planner's read filters.
 *
 * Every filter is validated even though it only reads, because an unvalidated
 * `sort` or `from` reaches the query builder. Ids are checked against the
 * user's own workspaces, so filtering by another tenant's campaign returns a
 * validation error rather than an empty (but confirming) result set.
 */
class PlannerFilterRequest extends FormRequest
{
    public const VIEWS = ['month', 'week', 'agenda', 'list', 'feed', 'grid', 'campaign'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $visible = $this->user()->visibleWorkspaceIds()->all();

        return [
            'view'           => ['nullable', Rule::in(self::VIEWS)],
            'from'           => ['nullable', 'date'],
            'to'             => ['nullable', 'date', 'after_or_equal:from'],
            'workspace_ids'  => ['nullable', 'array', 'max:50'],
            'workspace_ids.*'=> [Rule::in($visible)],
            'providers'      => ['nullable', 'array', 'max:20'],
            'providers.*'    => ['string', 'max:40'],
            'statuses'       => ['nullable', 'array', 'max:10'],
            'statuses.*'     => [Rule::in([
                Post::STATUS_DRAFT,
                Post::STATUS_PENDING_APPROVAL,
                Post::STATUS_SCHEDULED,
                Post::STATUS_PUBLISHING,
                Post::STATUS_PUBLISHED,
                Post::STATUS_PARTIALLY_FAILED,
                Post::STATUS_FAILED,
            ])],
            'campaign_ids'   => ['nullable', 'array', 'max:50'],
            'campaign_ids.*' => ['integer', Rule::exists('campaigns', 'id')->whereIn('workspace_id', $visible)],
            'pillar_ids'     => ['nullable', 'array', 'max:50'],
            'pillar_ids.*'   => ['integer', Rule::exists('content_pillars', 'id')->whereIn('workspace_id', $visible)],
            'tag_ids'        => ['nullable', 'array', 'max:50'],
            'tag_ids.*'      => ['integer', Rule::exists('tags', 'id')->whereIn('workspace_id', $visible)],
            'assignee_ids'   => ['nullable', 'array', 'max:50'],
            'assignee_ids.*' => ['integer'],
            'search'         => ['nullable', 'string', 'max:200'],
            'per_page'       => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }

    /** The requested view, defaulting to the month calendar. */
    public function view(): string
    {
        return $this->input('view', 'month');
    }

    /**
     * The date window to load. Views that are inherently bounded (month, week)
     * derive their own window so the payload stays proportional to what is on
     * screen — the audit's P1 finding was that the old page shipped 200 posts
     * regardless of what the user was looking at.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function window(): array
    {
        $anchor = $this->filled('from')
            ? Carbon::parse($this->input('from'))
            : Carbon::now();

        return match ($this->view()) {
            'week'   => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            'agenda' => [$anchor->copy()->startOfDay(), $anchor->copy()->addDays(14)->endOfDay()],
            'month', 'feed', 'grid' => [
                $anchor->copy()->startOfMonth()->startOfWeek(),
                $anchor->copy()->endOfMonth()->endOfWeek(),
            ],
            // list + campaign views are explicitly range-driven.
            default => [
                $this->filled('from') ? Carbon::parse($this->input('from')) : $anchor->copy()->subMonths(3),
                $this->filled('to') ? Carbon::parse($this->input('to')) : $anchor->copy()->addMonths(3),
            ],
        };
    }
}

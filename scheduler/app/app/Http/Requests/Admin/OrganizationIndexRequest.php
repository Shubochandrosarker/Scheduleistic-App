<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validated filters for the platform admin's organization index. Replaces
 * the previous unbounded `latest()->limit(500)->get()` with real server-side
 * pagination and filtering.
 */
class OrganizationIndexRequest extends FormRequest
{
    public const SORTABLE = ['name', 'plan', 'created_at', 'workspaces_count'];

    public function authorize(): bool
    {
        return true; // platform.admin middleware already gates the whole route.
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            // Effective plan: what the organization actually has access to
            // right now (override if active, else base). Distinct from
            // base_plan, which is the Stripe-billed plan regardless of any
            // admin grant layered on top — the two differ exactly when an
            // override is active, which is the case worth being able to find.
            'plan' => ['nullable', 'string', Rule::in(array_keys(config('plans')))],
            'base_plan' => ['nullable', 'string', Rule::in(array_keys(config('plans')))],
            'subscription_status' => ['nullable', 'string', 'max:40'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }

    public function sort(): string
    {
        return $this->validated('sort') ?? 'created_at';
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

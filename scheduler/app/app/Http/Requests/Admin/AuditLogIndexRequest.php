<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validated filters for the platform admin's read-only audit-log viewer —
 * closes the gap `docs/SCHEDULEISTIC_2_ADMIN_GUIDE.md` §7 admits in writing:
 * "There is no admin UI for it yet — query it directly [in SQL]."
 */
class AuditLogIndexRequest extends FormRequest
{
    public const SORTABLE = ['created_at', 'action'];

    public function authorize(): bool
    {
        return true; // platform.admin middleware already gates the route.
    }

    public function rules(): array
    {
        return [
            'actor' => ['nullable', 'string', 'max:200'],
            'organization_id' => ['nullable', 'integer', 'exists:teams,id'],
            'action' => ['nullable', 'string', 'max:100'],
            'subject_type' => ['nullable', 'string', 'max:200'],
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

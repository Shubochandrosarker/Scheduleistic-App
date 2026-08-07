<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validated filters for the platform admin's user index. Every filter is
 * validated even though it only reads, because an unvalidated `sort` reaches
 * the query builder directly.
 */
class UserIndexRequest extends FormRequest
{
    public const SORTABLE = ['name', 'email', 'created_at', 'suspended_at'];

    public function authorize(): bool
    {
        return true; // platform.admin middleware already gates the whole route.
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            'verified' => ['nullable', Rule::in(['yes', 'no'])],
            'platform_admin' => ['nullable', Rule::in(['yes', 'no'])],
            'organization_id' => ['nullable', 'integer', 'exists:teams,id'],
            'role' => ['nullable', 'string', 'max:40'],
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

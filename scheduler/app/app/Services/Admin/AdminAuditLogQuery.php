<?php

namespace App\Services\Admin;

use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the platform admin's audit-log query: filter by actor, organization,
 * action, subject type, and date, with real pagination. Read-only by design
 * — nothing in this class or its controller ever updates or deletes a row,
 * matching the append-only contract `AuditLog` itself documents.
 */
class AdminAuditLogQuery
{
    public function paginate(AuditLogIndexRequest $request): LengthAwarePaginator
    {
        $filters = $request->validated();

        return AuditLog::query()
            ->with(['user:id,name,email', 'team:id,name'])
            ->when($filters['actor'] ?? null, fn (Builder $q, $actor) => $q->whereHas(
                'user', fn (Builder $q) => $q->where('name', 'like', "%{$actor}%")->orWhere('email', 'like', "%{$actor}%"),
            ))
            ->when($filters['organization_id'] ?? null, fn (Builder $q, $orgId) => $q->where('team_id', $orgId))
            ->when($filters['action'] ?? null, fn (Builder $q, $action) => $q->where('action', 'like', "%{$action}%"))
            ->when($filters['subject_type'] ?? null, fn (Builder $q, $type) => $q->where('subject_type', 'like', "%{$type}%"))
            ->when($filters['created_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['created_to'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderBy($request->sort(), $request->direction())
            ->paginate($request->perPage())
            ->withQueryString();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Services\Admin\AdminAuditLogQuery;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only, paginated view over the append-only `audit_logs` table. There
 * is deliberately no update or delete route — see AuditLog's own docblock.
 */
class AuditLogController extends Controller
{
    public function __construct(private readonly AdminAuditLogQuery $query) {}

    public function index(AuditLogIndexRequest $request): Response
    {
        $logs = $this->query->paginate($request);

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $log->user?->only(['id', 'name', 'email']),
                'organization' => $log->team?->only(['id', 'name']),
                'subject_type' => $log->subject_type ? class_basename($log->subject_type) : null,
                'subject_id' => $log->subject_id,
                'context' => $log->context,
                'ip' => $log->ip,
                'created_at' => $log->created_at,
            ]),
            'filters' => $request->validated(),
            // Distinct actions already recorded, for the filter dropdown —
            // bounded (there are only ever a few dozen distinct action
            // strings in the whole app) and cheap against the indexed column.
            'actionOptions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}

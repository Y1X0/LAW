<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\AuditLog;

/**
 * قراءة سجلّ التدقيق (ADMIN-5) — نقطة جديدة محميّة بصلاحية audit.view القائمة.
 * قراءة فقط (السجلّ Append-Only أصلاً). لا Migration، لا تغيير لأي مسار قائم.
 */
class AuditController
{
    /** GET /api/admin/audit — قائمة أحداث التدقيق مع فلترة وترقيم. */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('user:id,name')->latest('id');

        if ($action = trim((string) $request->query('action'))) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $page = $query->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'auditable_type' => class_basename((string) $log->auditable_type),
                'auditable_id' => $log->auditable_id,
                'ip_address' => $log->ip_address,
                'created_at' => optional($log->created_at)->toIso8601String(),
            ])->all(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
            'errors' => null,
        ]);
    }
}

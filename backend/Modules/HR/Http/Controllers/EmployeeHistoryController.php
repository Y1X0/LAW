<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\AuditLog;
use Modules\HR\Models\Employee;

/**
 * سجل الموظف (Timeline) مبنيّ على سجل التدقيق (Issue #14). قراءة: employees.view.
 */
class EmployeeHistoryController
{
    public function index(Request $request, Employee $employee): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $page = AuditLog::where('auditable_type', Employee::class)
            ->where('auditable_id', $employee->id)
            ->orderByDesc('id')
            ->paginate($perPage, ['id', 'user_id', 'action', 'new_values', 'created_at']);

        return response()->json([
            'data' => $page->items(),
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

<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Core\Models\Department;

/**
 * إدارة الأقسام ضمن الفروع (الهيكل التنظيمي) — بصلاحية org.manage.
 * اسم القسم فريد داخل الفرع الواحد (نفس مفتاح الاستيراد الطبيعي branch_id + name).
 */
class DepartmentController
{
    use RecordsAudit;

    public function index(Request $request): JsonResponse
    {
        $query = Department::with('branch:id,name')->withCount([]);
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        return $this->ok($query->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:120', $this->uniqueInBranch($request)],
            'parent_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active' => ['boolean'],
        ]);

        $department = Department::create($data);
        $this->recordAudit($request, 'department_created', Department::class, $department->id, ['name' => $department->name, 'branch_id' => $department->branch_id]);

        return $this->ok($department->load('branch:id,name'), 201);
    }

    public function show(Department $department): JsonResponse
    {
        return $this->ok($department->load('branch:id,name'));
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $branchId = $request->input('branch_id', $department->branch_id);
        $data = $request->validate([
            'branch_id' => ['sometimes', 'integer', 'exists:branches,id'],
            'name' => ['sometimes', 'string', 'max:120', Rule::unique('departments', 'name')->where('branch_id', $branchId)->ignore($department->id)],
            'parent_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active' => ['boolean'],
        ]);

        $department->update($data);
        $this->recordAudit($request, 'department_updated', Department::class, $department->id, $data);

        return $this->ok($department->load('branch:id,name'));
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        // حماية النزاهة: لا يُحذف قسم مرتبط بموظفين.
        if (DB::table('employees')->where('department_id', $department->id)->exists()) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => ['code' => 'DEPARTMENT_IN_USE', 'message' => 'لا يمكن حذف قسم مرتبط بموظفين. عطّله بدلاً من الحذف.'],
            ], 422);
        }

        $id = $department->id;
        $department->delete();
        $this->recordAudit($request, 'department_deleted', Department::class, $id);

        return $this->ok(['message' => 'تم حذف القسم.']);
    }

    /** اسم القسم فريد داخل الفرع المحدّد. */
    private function uniqueInBranch(Request $request): object
    {
        return Rule::unique('departments', 'name')->where('branch_id', $request->input('branch_id'));
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}

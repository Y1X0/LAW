<?php

namespace Modules\HR\Services;

use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;

/**
 * الخدمة العامة لوحدة HR (Issue #13): إنشاء/تعديل/أرشفة الموظفين مع التدقيق.
 * تُتيحها الوحدة للوحدات الأخرى (docs/module-boundaries.md).
 */
class EmployeeService
{
    use RecordsAudit;

    public function create(array $data, Request $request): Employee
    {
        $data['created_by'] = $request->user()?->id;
        $employee = Employee::create($data);

        $this->recordAudit($request, 'employee_created', Employee::class, $employee->id, [
            'employee_no' => $employee->employee_no,
            'name' => $employee->full_name_ar,
        ]);

        return $employee;
    }

    public function update(Employee $employee, array $data, Request $request): Employee
    {
        $data['updated_by'] = $request->user()?->id;
        $employee->update($data);

        $this->recordAudit($request, 'employee_updated', Employee::class, $employee->id, [
            'changed' => array_keys($data),
        ]);

        return $employee->refresh();
    }

    /** أرشفة (Soft Delete) — لا حذف نهائي للموظفين. */
    public function archive(Employee $employee, Request $request): void
    {
        $id = $employee->id;
        $employee->delete();

        $this->recordAudit($request, 'employee_archived', Employee::class, $id);
    }
}

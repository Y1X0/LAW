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

    /** الحقول الوحيدة التي يُسمح للموظف بتعديلها ذاتياً (#52) — لا حقول مالية/تنظيمية. */
    public const SELF_EDITABLE_FIELDS = [
        'phone', 'address', 'photo_path', 'emergency_contact_name', 'emergency_contact_phone',
    ];

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

    /**
     * تعديل ذاتي محدود من الموظف نفسه (#52): يقبل حقول SELF_EDITABLE_FIELDS **فقط**.
     * أي حقل حسّاس (الراتب/القسم/الفرع/الوظيفة/المدير) يُستبعَد تماماً — دفاع بالطبقات.
     */
    public function updateSelfProfile(Employee $employee, array $data, Request $request): Employee
    {
        $filtered = array_intersect_key($data, array_flip(self::SELF_EDITABLE_FIELDS));
        $filtered['updated_by'] = $request->user()?->id;
        $employee->update($filtered);

        $this->recordAudit($request, 'employee_profile_self_updated', Employee::class, $employee->id, [
            'changed' => array_values(array_intersect(array_keys($data), self::SELF_EDITABLE_FIELDS)),
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

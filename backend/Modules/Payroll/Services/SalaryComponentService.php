<?php

namespace Modules\Payroll\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\EmployeeSalaryComponent;
use Modules\Payroll\Models\SalaryComponent;

/**
 * إسناد مكوّنات الراتب للموظفين (Issue #33): إسناد بقيمة/فعالية، وإيقاف.
 * تاريخي: إعادة إسناد نفس المكوّن تؤرشف السابق (لا حذف) لسلامة الـ snapshot. لا حساب هنا (#36).
 */
class SalaryComponentService
{
    use RecordsAudit;

    /** إسناد مكوّن نشط لموظف (يؤرشف الإسناد السابق لنفس المكوّن). */
    public function assign(Employee $employee, SalaryComponent $component, array $data, Request $request): EmployeeSalaryComponent
    {
        $effectiveFrom = $data['effective_from'] ?? Carbon::now()->toDateString();

        // أرشفة الإسناد النشط السابق لنفس المكوّن فقط (مكوّنات أخرى تبقى فعّالة).
        EmployeeSalaryComponent::where('employee_id', $employee->id)
            ->where('salary_component_id', $component->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'effective_to' => Carbon::parse($effectiveFrom)->subDay()->toDateString(),
            ]);

        $assignment = EmployeeSalaryComponent::create([
            'employee_id' => $employee->id,
            'salary_component_id' => $component->id,
            'value' => $data['value'],
            'effective_from' => $effectiveFrom,
            'is_active' => true,
            'created_by' => $request->user()?->id,
        ]);

        $this->recordAudit($request, 'employee_component_assigned', EmployeeSalaryComponent::class, $assignment->id, [
            'employee_id' => $employee->id, 'salary_component_id' => $component->id, 'value' => $data['value'],
        ]);

        return $assignment;
    }

    /** إيقاف إسناد مكوّن (أرشفة بنهاية فعالية — لا حذف). */
    public function deactivate(EmployeeSalaryComponent $assignment, Request $request): EmployeeSalaryComponent
    {
        $assignment->update([
            'is_active' => false,
            'effective_to' => $assignment->effective_to?->toDateString() ?? Carbon::now()->toDateString(),
        ]);

        $this->recordAudit($request, 'employee_component_deactivated', EmployeeSalaryComponent::class, $assignment->id, [
            'employee_id' => $assignment->employee_id, 'salary_component_id' => $assignment->salary_component_id,
        ]);

        return $assignment;
    }
}

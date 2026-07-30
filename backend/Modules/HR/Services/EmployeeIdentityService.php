<?php

namespace Modules\HR\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;

/**
 * ربط هوية الموظف بحساب المستخدم (Epic 9 / Issue #47) — أساس الخدمة الذاتية.
 *
 * يفرض علاقة **1:1** بين المستخدم والموظف:
 *  - مستخدم واحد لا يُربط بأكثر من موظف (يدعمه أيضاً قيد unique على العمود).
 *  - موظف مرتبط بحساب لا يُعاد ربطه بحساب آخر إلا بعد **فكّ الربط صراحةً** (إعادة ربط نظيفة).
 * لا يُنشئ أي نقطة نهاية للخدمة الذاتية — هذه طبقة الهوية فقط.
 */
class EmployeeIdentityService
{
    use RecordsAudit;

    /** ربط موظف بحساب مستخدم (idempotent لنفس الزوج). */
    public function link(Employee $employee, User $user, ?Request $request = null): Employee
    {
        // نفس الربط القائم → لا عملية.
        if ($employee->user_id === $user->id) {
            return $employee;
        }

        // جانب الموظف: مرتبط بحساب آخر → يجب فكّ الربط أولاً (إعادة ربط نظيفة، بلا استبدال صامت).
        abort_if(
            $employee->user_id !== null,
            422,
            'هذا الموظف مرتبط بحساب آخر — افصل الربط أولاً قبل إعادة الربط.'
        );

        // جانب المستخدم: مرتبط بموظف آخر (بما فيها المحذوف soft) → رفض 1:1.
        abort_if(
            Employee::withTrashed()->where('user_id', $user->id)->exists(),
            422,
            'هذا الحساب مرتبط بموظف آخر بالفعل.'
        );

        $employee->user_id = $user->id;
        $employee->save();

        if ($request !== null) {
            $this->recordAudit($request, 'employee_identity_linked', Employee::class, $employee->id, [
                'user_id' => $user->id,
            ]);
        }

        return $employee;
    }

    /** فكّ ربط الموظف عن حسابه (لا يفسد سجل الموظف — user_id يصبح null). */
    public function unlink(Employee $employee, ?Request $request = null): Employee
    {
        $previous = $employee->user_id;
        if ($previous === null) {
            return $employee;
        }

        $employee->user_id = null;
        $employee->save();

        if ($request !== null) {
            $this->recordAudit($request, 'employee_identity_unlinked', Employee::class, $employee->id, [
                'previous_user_id' => $previous,
            ]);
        }

        return $employee;
    }
}

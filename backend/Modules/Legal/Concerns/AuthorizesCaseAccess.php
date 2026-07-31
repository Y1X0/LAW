<?php

namespace Modules\Legal\Concerns;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Modules\Legal\Models\LegalCase;

/**
 * عزل رؤية القضايا — مشترك بين متحكّمات القضايا والجلسات (LC-2/LC-3).
 *
 * القاعدة: من يملك cases.view_all يرى الكل؛ ومن يملك cases.view_own فقط يرى
 * القضايا المسندة إليه (عبر case_assignments)، ويجب أن يكون مرتبطاً بموظف.
 */
trait AuthorizesCaseAccess
{
    /** يعيد ردّ 403 موحّداً عند منع رؤية القضية، أو null عند السماح. */
    protected function guardCaseView(User $user, LegalCase $case): ?JsonResponse
    {
        if ($user->hasPermission('cases.view_all')) {
            return null;
        }
        $employee = $user->employee;
        if ($employee === null) {
            return $this->caseForbidden('NO_LINKED_EMPLOYEE');
        }

        return $case->isAssignedTo($employee->id) ? null : $this->caseForbidden('FORBIDDEN');
    }

    protected function caseForbidden(string $code): JsonResponse
    {
        return response()->json(['data' => null, 'meta' => null, 'errors' => ['code' => $code]], 403);
    }
}

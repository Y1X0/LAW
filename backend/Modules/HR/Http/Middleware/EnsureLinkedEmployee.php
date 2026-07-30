<?php

namespace Modules\HR\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حارس الخدمة الذاتية (Issue #47): يتطلب أن يكون للمستخدم الحالي **سجلّ موظف مرتبط**.
 *
 * يُعاد استخدامه في كل مسارات الخدمة الذاتية (#48–#52) لضمان أن الوصول يبدأ دائماً من
 * `Auth::user()->employee`. مستخدم بلا موظف مرتبط (Super Admin/Auditor) → 403.
 * الاستخدام: ->middleware(['auth.token', 'employee.linked'])
 */
class EnsureLinkedEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->user()?->employee;

        if ($employee === null) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => [
                    'code' => 'NO_LINKED_EMPLOYEE',
                    'message' => 'هذا الحساب غير مرتبط بسجلّ موظف.',
                ],
            ], 403);
        }

        return $next($request);
    }
}

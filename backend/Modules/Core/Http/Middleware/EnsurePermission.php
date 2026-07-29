<?php

namespace Modules\Core\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Modules\Core\Models\AuditLog;
use Symfony\Component\HttpFoundation\Response;

/**
 * يتطلب أن يملك المستخدم واحدة على الأقل من الصلاحيات المحددة (دلالة ANY).
 * الاستخدام: ->middleware('permission:roles.manage')
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyPermission($permissions)) {
            if ($user) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'permission_denied',
                    'auditable_type' => User::class,
                    'auditable_id' => $user->id,
                    'new_values' => ['required' => $permissions, 'path' => $request->path()],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'لا تملك صلاحية تنفيذ هذا الإجراء.',
                ],
            ], 403);
        }

        return $next($request);
    }
}

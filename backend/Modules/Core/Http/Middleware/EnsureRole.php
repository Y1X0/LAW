<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يتطلب أن يملك المستخدم واحداً على الأقل من الأدوار المحددة.
 * الاستخدام: ->middleware('role:admin')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole($roles)) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'لا تملك الدور المطلوب لهذا الإجراء.',
                ],
            ], 403);
        }

        return $next($request);
    }
}

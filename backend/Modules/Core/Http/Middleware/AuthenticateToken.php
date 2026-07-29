<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Services\AuthService;
use Symfony\Component\HttpFoundation\Response;

/**
 * حماية المسارات عبر توكن الوصول (Bearer). يحلّ التوكن إلى مستخدم،
 * يربطه بالطلب، ويحدّث آخر استخدام. يردّ 401 عند عدم الصلاحية.
 */
class AuthenticateToken
{
    public function __construct(private readonly AuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer || ! ($token = $this->auth->resolveAccessToken($bearer))) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'مطلوب مصادقة صالحة.',
                ],
            ], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        $user = $token->user;
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('auth_token', $token);

        return $next($request);
    }
}

<?php

namespace Modules\Core\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Exceptions\AuthException;
use Modules\Core\Models\AuthToken;
use Modules\Core\Services\AuthService;

class AuthController
{
    public function __construct(private readonly AuthService $auth) {}

    /** POST /api/auth/login */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->auth->login($data['email'], $data['password'], $request);
        } catch (AuthException $e) {
            return $this->error($e);
        }

        return $this->ok([
            'user' => $this->userPayload($result['user']),
            'tokens' => $result['tokens'],
        ]);
    }

    /** POST /api/auth/refresh */
    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate(['refresh_token' => ['required', 'string']]);

        try {
            $result = $this->auth->refresh($data['refresh_token'], $request);
        } catch (AuthException $e) {
            return $this->error($e);
        }

        return $this->ok(['tokens' => $result['tokens']]);
    }

    /** POST /api/auth/logout (auth.token) */
    public function logout(Request $request): JsonResponse
    {
        /** @var AuthToken $token */
        $token = $request->attributes->get('auth_token');
        $this->auth->logout($token, $request);

        return $this->ok(['message' => 'تم تسجيل الخروج.']);
    }

    /** GET /api/auth/me (auth.token) */
    public function me(Request $request): JsonResponse
    {
        return $this->ok(['user' => $this->userPayload($request->user())]);
    }

    private function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status,
            'mfa_enabled' => (bool) $user->mfa_enabled,
        ];
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null]);
    }

    private function error(AuthException $e): JsonResponse
    {
        return response()->json([
            'data' => null,
            'meta' => null,
            'errors' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
        ], $e->status);
    }
}

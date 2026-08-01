<?php

namespace Modules\Core\Http\Controllers\Rbac;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Concerns\GuardsLastAdmin;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Core\Models\Role;

/**
 * إسناد/إزالة أدوار المستخدمين (RBAC — Issue #12). محميّ بصلاحية users.manage.
 */
class UserRoleController
{
    use GuardsLastAdmin, RecordsAudit;

    /** GET /api/users/{user}/roles */
    public function index(User $user): JsonResponse
    {
        return response()->json([
            'data' => $user->roles()->get(['roles.id', 'name', 'display_name']),
            'meta' => null, 'errors' => null,
        ]);
    }

    /** POST /api/users/{user}/roles — إسناد دور (مع فرع اختياري). */
    public function store(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $role = Role::findOrFail($data['role_id']);
        $user->assignRole($role, $data['branch_id'] ?? null);

        $this->recordAudit($request, 'user_role_assigned', User::class, $user->id, [
            'role' => $role->name, 'branch_id' => $data['branch_id'] ?? null,
        ]);

        return response()->json([
            'data' => ['message' => 'تم إسناد الدور.'],
            'meta' => null, 'errors' => null,
        ], 201);
    }

    /** DELETE /api/users/{user}/roles/{role} — إزالة دور. */
    public function destroy(Request $request, User $user, Role $role): JsonResponse
    {
        // حماية إضافية: لا تُزال قدرة users.manage عن آخر مدير فعّال.
        if (! $this->activeAdminRemainsAfterPivotRemoval($user->id, $role->id)) {
            return response()->json([
                'data' => null, 'meta' => null,
                'errors' => ['code' => 'LAST_ADMIN_PROTECTED', 'message' => 'لا يمكن إزالة صلاحية إدارة المستخدمين عن آخر مدير فعّال.'],
            ], 422);
        }

        $user->removeRole($role);
        $this->recordAudit($request, 'user_role_removed', User::class, $user->id, ['role' => $role->name]);

        return response()->json([
            'data' => ['message' => 'تمت إزالة الدور.'],
            'meta' => null, 'errors' => null,
        ]);
    }
}

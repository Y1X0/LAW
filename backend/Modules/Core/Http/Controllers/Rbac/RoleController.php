<?php

namespace Modules\Core\Http\Controllers\Rbac;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Concerns\GuardsLastAdmin;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;

/**
 * إدارة الأدوار وربط الصلاحيات بها (RBAC — Issue #12).
 * محميّ بصلاحية roles.manage.
 */
class RoleController
{
    use GuardsLastAdmin, RecordsAudit;

    public function index(): JsonResponse
    {
        return $this->ok(Role::with('permissions:id,name,module')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:120'],
        ]);

        $role = Role::create($data);
        $this->recordAudit($request, 'role_created', Role::class, $role->id, ['name' => $role->name]);

        return $this->ok($role, 201);
    }

    public function show(Role $role): JsonResponse
    {
        return $this->ok($role->load('permissions:id,name,module'));
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($role->id)],
            'display_name' => ['sometimes', 'string', 'max:120'],
        ]);

        if ($role->is_system && isset($data['name'])) {
            unset($data['name']); // لا يُعاد تسمية الأدوار النظامية
        }

        $role->update($data);
        $this->recordAudit($request, 'role_updated', Role::class, $role->id, $data);

        return $this->ok($role);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json([
                'data' => null, 'meta' => null,
                'errors' => ['code' => 'ROLE_PROTECTED', 'message' => 'لا يمكن حذف دور نظامي.'],
            ], 422);
        }

        // حماية إضافية: حذف دور يمنح users.manage يجب ألا يُسقط آخر مدير فعّال.
        if ($this->roleGrantsAdmin($role) && ! $this->activeAdminRemainsWithoutRole($role)) {
            return response()->json([
                'data' => null, 'meta' => null,
                'errors' => ['code' => 'LAST_ADMIN_PROTECTED', 'message' => 'لا يمكن حذف آخر دور يمنح إدارة المستخدمين لمدير فعّال.'],
            ], 422);
        }

        $id = $role->id;
        $role->delete();
        $this->recordAudit($request, 'role_deleted', Role::class, $id);

        return $this->ok(['message' => 'تم حذف الدور.']);
    }

    /** PUT /api/roles/{role}/permissions — مزامنة صلاحيات الدور. */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $adminPermId = $this->adminPermissionId();
        $newIds = array_map('intval', $data['permissions']);
        $roleGrantsAdmin = $this->roleGrantsAdmin($role);

        // SEC-1: منع تصعيد الصلاحيات. صلاحية users.manage هي صلاحية «مالك المنصّة»
        // (ADR-006). إضافتها إلى دور لا يمنحها حالياً مسموحة فقط لمن يملكها فعلاً —
        // حتى لا يستطيع حامل roles.manage (فقط) أن يمنحها لدوره ويصبح مديراً كاملاً.
        $grantsAdminNow = $adminPermId !== null && in_array($adminPermId, $newIds, true);
        if ($grantsAdminNow && ! $roleGrantsAdmin && ! $request->user()?->hasPermission('users.manage')) {
            return response()->json([
                'data' => null, 'meta' => null,
                'errors' => ['code' => 'FORBIDDEN', 'message' => 'لا يمكن منح صلاحية إدارة المستخدمين إلا لمالك منصّة يملكها بالفعل.'],
            ], 403);
        }

        // حماية إضافية: تجريد الدور من users.manage يجب ألا يُسقط آخر مدير فعّال.
        $stripsAdmin = $roleGrantsAdmin
            && $adminPermId !== null
            && ! in_array($adminPermId, $newIds, true);
        if ($stripsAdmin && ! $this->activeAdminRemainsWithoutRole($role)) {
            return response()->json([
                'data' => null, 'meta' => null,
                'errors' => ['code' => 'LAST_ADMIN_PROTECTED', 'message' => 'لا يمكن نزع صلاحية إدارة المستخدمين عن آخر دور يمنحها لمدير فعّال.'],
            ], 422);
        }

        $role->permissions()->sync($data['permissions']);
        $this->recordAudit($request, 'role_permissions_synced', Role::class, $role->id, [
            'permissions' => Permission::whereIn('id', $data['permissions'])->pluck('name'),
        ]);

        return $this->ok($role->load('permissions:id,name,module'));
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}

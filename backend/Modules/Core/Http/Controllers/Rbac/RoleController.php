<?php

namespace Modules\Core\Http\Controllers\Rbac;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;

/**
 * إدارة الأدوار وربط الصلاحيات بها (RBAC — Issue #12).
 * محميّ بصلاحية roles.manage.
 */
class RoleController
{
    use RecordsAudit;

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

<?php

namespace Modules\Core\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;

/**
 * حارس «آخر مدير» (ADMIN-3) — يمنع أي عملية RBAC من إزالة قدرة إدارة المستخدمين
 * (users.manage) عن آخر مدير فعّال، فيقي المنصّة من الإقفال الكامل.
 *
 * إضافي بحت: يُستدعى قبل العمليات الهدّامة فقط، ولا يغيّر سلوك أي مسار في الحالات
 * المشروعة (لا يتأثّر إلا السيناريو الكارثي الوحيد: إسقاط آخر مدير).
 */
trait GuardsLastAdmin
{
    private const ADMIN_PERMISSION = 'users.manage';

    /** معرّفات الأدوار التي تمنح users.manage حالياً. */
    private function adminRoleIds(): array
    {
        return Role::whereHas('permissions', fn ($q) => $q->where('name', self::ADMIN_PERMISSION))
            ->pluck('id')->all();
    }

    /** هل يملك مستخدم فعّال (غير محذوف) أياً من هذه الأدوار؟ */
    private function activeAdminExistsWithin(array $roleIds): bool
    {
        if ($roleIds === []) {
            return false;
        }

        return User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))
            ->exists();
    }

    /**
     * بعد إزالة الإسناد (userId ↔ roleId): هل يبقى أي مدير فعّال؟
     * إزالة دور لا يمنح users.manage لا تُقلّل المديرين (تعود true فوراً).
     */
    private function activeAdminRemainsAfterPivotRemoval(int $userId, int $roleId): bool
    {
        $roleIds = $this->adminRoleIds();
        if (! in_array($roleId, $roleIds, true)) {
            return true;
        }

        return DB::table('user_role')
            ->join('users', 'users.id', '=', 'user_role.user_id')
            ->whereIn('user_role.role_id', $roleIds)
            ->where('users.status', 'active')
            ->whereNull('users.deleted_at')
            ->where(fn ($q) => $q->where('user_role.user_id', '!=', $userId)
                ->orWhere('user_role.role_id', '!=', $roleId))
            ->exists();
    }

    /**
     * بعد إزالة الدور $role كلّياً من مصادر users.manage (حذفه أو تجريده من الصلاحية):
     * هل يبقى مدير فعّال عبر دور آخر؟
     */
    private function activeAdminRemainsWithoutRole(Role $role): bool
    {
        $remaining = array_values(array_filter(
            $this->adminRoleIds(),
            fn (int $id) => $id !== $role->id,
        ));

        return $this->activeAdminExistsWithin($remaining);
    }

    /** هل يمنح هذا الدور صلاحية users.manage حالياً؟ */
    private function roleGrantsAdmin(Role $role): bool
    {
        return $role->permissions()->where('name', self::ADMIN_PERMISSION)->exists();
    }

    /** معرّف صلاحية users.manage (أو null إن لم توجد). */
    private function adminPermissionId(): ?int
    {
        return Permission::where('name', self::ADMIN_PERMISSION)->value('id');
    }
}

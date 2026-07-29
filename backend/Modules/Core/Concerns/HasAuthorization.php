<?php

namespace Modules\Core\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Models\Role;

/**
 * دوال التفويض (RBAC) للمستخدم — Issue #12.
 * تُدمج في App\Models\User وتوفّر فحص الأدوار والصلاحيات وإسنادها.
 */
trait HasAuthorization
{
    /** ذاكرة مؤقتة لأسماء الصلاحيات خلال الطلب الواحد. */
    protected ?array $cachedPermissionNames = null;

    /** الأدوار المُسندة للمستخدم (مع تقييد اختياري بالفرع). */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')
            ->withPivot(['branch_id', 'created_at']);
    }

    /** أسماء كل صلاحيات المستخدم المشتقّة من أدواره (مع تخزين مؤقت). */
    public function permissionNames(): array
    {
        if ($this->cachedPermissionNames === null) {
            $this->cachedPermissionNames = $this->roles()
                ->with('permissions:id,name')
                ->get()
                ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
                ->unique()
                ->values()
                ->all();
        }

        return $this->cachedPermissionNames;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissionNames(), true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(string|array $names): bool
    {
        $names = (array) $names;

        return $this->roles()->whereIn('name', $names)->exists();
    }

    /** إسناد دور للمستخدم (مع فرع اختياري)؛ عملية Idempotent. */
    public function assignRole(Role|string $role, ?int $branchId = null): void
    {
        $role = $role instanceof Role ? $role : Role::where('name', $role)->firstOrFail();

        $exists = $this->roles()
            ->wherePivot('role_id', $role->id)
            ->wherePivot('branch_id', $branchId)
            ->exists();

        if (! $exists) {
            $this->roles()->attach($role->id, ['branch_id' => $branchId]);
        }

        $this->cachedPermissionNames = null;
    }

    /** إزالة دور من المستخدم (ضمن فرع محدد أو مطلقاً). */
    public function removeRole(Role|string $role, ?int $branchId = null): void
    {
        $role = $role instanceof Role ? $role : Role::where('name', $role)->firstOrFail();

        $query = $this->roles()->newPivotStatement()
            ->where('user_id', $this->id)
            ->where('role_id', $role->id);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $query->delete();
        $this->cachedPermissionNames = null;
    }
}

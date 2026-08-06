<?php

namespace Modules\Core\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Core\Models\AuthToken;
use Modules\Core\Models\Role;
use Modules\HR\Models\Employee;
use Modules\HR\Services\EmployeeIdentityService;

/**
 * إدارة المستخدمين لوحدة تحكّم المنصّة (ADMIN-2). محميّ بصلاحية users.manage.
 *
 * إضافة فقط — لا يغيّر أي نقطة نهاية قائمة ولا طريقة تسجيل الدخول ولا الصلاحيات.
 * التعطيل/التفعيل يعتمد على عمود status الموجود (active/suspended/locked) الذي
 * تحترمه بوّابة الدخول أصلاً؛ لا Migration ولا حذف نهائي.
 */
class UserController
{
    use RecordsAudit;

    /** الصلاحية التي تُعرّف «المدير» لأغراض حماية آخر مدير. */
    private const ADMIN_PERMISSION = 'users.manage';

    public function __construct(private readonly EmployeeIdentityService $identities) {}

    /** GET /api/users — قائمة مع بحث وترقيم. */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with(['roles:id,name,display_name', 'employee:id,user_id,employee_no,full_name_ar']);

        if ($search = trim((string) $request->query('search'))) {
            // LOWER(...) LIKE: بحث غير حسّاس لحالة الأحرف عبر Postgres وsqlite معاً.
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(username) LIKE ?', [$needle]);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $page = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (User $u) => $this->present($u))->all(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    /** GET /api/users/{user} — تفاصيل المستخدم مع أدواره والموظف المرتبط. */
    public function show(User $user): JsonResponse
    {
        return $this->ok($this->present($user));
    }

    /** POST /api/users — إنشاء مستخدم جديد. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:60', 'unique:users,username'],
            'password' => ['required', 'string', PasswordRule::defaults(), 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'suspended', 'locked'])],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        // معاملة: إنشاء المستخدم وإسناد أدواره عملية ذرّية (لا حالة جزئية عند الفشل).
        $user = DB::transaction(function () use ($data) {
            $user = new User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->username = $data['username'] ?? null;
            $user->password = $data['password']; // hashed cast
            $user->status = $data['status'] ?? 'active';
            $user->password_changed_at = now();
            $user->save();

            foreach ($data['role_ids'] ?? [] as $roleId) {
                $user->assignRole(Role::findOrFail($roleId));
            }

            return $user;
        });

        $this->recordAudit($request, 'user_created', User::class, $user->id, [
            'email' => $user->email,
            'role_ids' => $data['role_ids'] ?? [],
        ]);

        return $this->ok($this->present($user->fresh(['roles', 'employee'])), 201);
    }

    /** PATCH /api/users/{user} — تعديل بيانات الملف (لا الحالة ولا كلمة المرور — لهما نقاط مخصّصة). */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['sometimes', 'nullable', 'string', 'max:60', Rule::unique('users', 'username')->ignore($user->id)],
        ]);

        $old = $user->only(array_keys($data)); // before-image للحقول المتغيّرة فقط
        $user->fill($data)->save();
        $this->recordAudit($request, 'user_updated', User::class, $user->id, $data, $old);

        return $this->ok($this->present($user->fresh(['roles', 'employee'])));
    }

    /** POST /api/users/{user}/disable — تعطيل (status=suspended) مع إبطال جلساته. */
    public function disable(Request $request, User $user): JsonResponse
    {
        // آخر مدير أولاً: يمنع إقفال المنصّة حتى لو حاول المدير الوحيد تعطيل نفسه.
        if ($this->isLastActiveAdmin($user)) {
            return $this->guard('LAST_ADMIN_PROTECTED', 'لا يمكن تعطيل آخر مدير فعّال يملك صلاحية إدارة المستخدمين.');
        }

        if ($user->id === $request->user()->id) {
            return $this->guard('SELF_DISABLE_FORBIDDEN', 'لا يمكنك تعطيل حسابك الخاص.');
        }

        $user->forceFill(['status' => 'suspended'])->save();
        // إبطال الجلسات النشطة ليأخذ التعطيل مفعوله فوراً (بوّابة الدخول تمنع الدخول لاحقاً أصلاً).
        AuthToken::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $this->recordAudit($request, 'user_disabled', User::class, $user->id);

        return $this->ok($this->present($user->fresh(['roles', 'employee'])));
    }

    /** POST /api/users/{user}/enable — إعادة التفعيل (status=active). */
    public function enable(Request $request, User $user): JsonResponse
    {
        $user->forceFill(['status' => 'active'])->save();
        $this->recordAudit($request, 'user_enabled', User::class, $user->id);

        return $this->ok($this->present($user->fresh(['roles', 'employee'])));
    }

    /** POST /api/users/{user}/reset-password — تعيين كلمة مرور جديدة إدارياً وإبطال الجلسات. */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', PasswordRule::defaults(), 'max:100', 'confirmed'],
        ]);

        $user->forceFill([
            'password' => $data['password'], // hashed cast
            'password_changed_at' => now(),
        ])->save();

        // إبطال كل الجلسات ليُجبَر المستخدم على الدخول بكلمة المرور الجديدة.
        AuthToken::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $this->recordAudit($request, 'user_password_reset', User::class, $user->id);

        return $this->ok(['message' => 'تم تعيين كلمة مرور جديدة.']);
    }

    /** POST /api/users/{user}/employee — ربط موظف بالحساب عبر خدمة الهوية الموجودة. */
    public function linkEmployee(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $this->identities->link($employee, $user, $request); // يفرض 1:1 ويسجّل التدقيق

        return $this->ok($this->present($user->fresh(['roles', 'employee'])));
    }

    /** DELETE /api/users/{user}/employee — فكّ ربط الموظف عن الحساب. */
    public function unlinkEmployee(Request $request, User $user): JsonResponse
    {
        $employee = Employee::where('user_id', $user->id)->first();
        if ($employee !== null) {
            $this->identities->unlink($employee, $request); // يسجّل التدقيق
        }

        return $this->ok($this->present($user->fresh(['roles', 'employee'])));
    }

    /**
     * هل هذا المستخدم آخر مدير فعّال؟ («مدير» = مستخدم فعّال يملك users.manage عبر أدواره.)
     * يمنع الإقفال الكامل للمنصّة عند تعطيل آخر من يديرها.
     */
    private function isLastActiveAdmin(User $user): bool
    {
        if ($user->status !== 'active' || ! $user->hasPermission(self::ADMIN_PERMISSION)) {
            return false;
        }

        $otherActiveAdmins = User::query()
            ->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->whereHas('roles', fn ($q) => $q->whereHas('permissions', fn ($p) => $p->where('name', self::ADMIN_PERMISSION)))
            ->count();

        return $otherActiveAdmins === 0;
    }

    /** تمثيل موحّد للمستخدم (بدون أي حقول حسّاسة — password/mfa_secret مخفيّة أصلاً). */
    private function present(User $user): array
    {
        $base = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status,
            'mfa_enabled' => (bool) $user->mfa_enabled,
            'last_login_at' => optional($user->last_login_at)->toIso8601String(),
            'created_at' => optional($user->created_at)->toIso8601String(),
            'roles' => $user->roles->map(fn ($r) => [
                'id' => $r->id, 'name' => $r->name, 'display_name' => $r->display_name,
            ])->values(),
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'employee_no' => $user->employee->employee_no,
                'full_name_ar' => $user->employee->full_name_ar,
            ] : null,
        ];

        return $base;
    }

    private function guard(string $code, string $message): JsonResponse
    {
        return response()->json([
            'data' => null, 'meta' => null,
            'errors' => ['code' => $code, 'message' => $message],
        ], 422);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}

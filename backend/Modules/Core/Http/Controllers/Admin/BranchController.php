<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Core\Models\Branch;

/**
 * إدارة الفروع (الهيكل التنظيمي) — تُدار من وحدة التحكّم بصلاحية org.manage.
 * تُكمل «تشغيل الشركة من المتصفّح»: بديل واجهي لبذرة OrgStructureSeeder (تبقى للإقلاع).
 */
class BranchController
{
    use RecordsAudit;

    public function index(): JsonResponse
    {
        return $this->ok(Branch::withCount(['departments'])->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', 'unique:branches,code'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:80'],
            'is_active' => ['boolean'],
        ]);

        $branch = Branch::create($data);
        $this->recordAudit($request, 'branch_created', Branch::class, $branch->id, ['name' => $branch->name, 'code' => $branch->code]);

        return $this->ok($branch, 201);
    }

    public function show(Branch $branch): JsonResponse
    {
        return $this->ok($branch->loadCount('departments'));
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('branches', 'code')->ignore($branch->id)],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:80'],
            'is_active' => ['boolean'],
        ]);

        $branch->update($data);
        $this->recordAudit($request, 'branch_updated', Branch::class, $branch->id, $data);

        return $this->ok($branch);
    }

    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        // حماية النزاهة: لا يُحذف فرع مرتبط بأقسام أو موظفين (يُعطَّل بدلاً من ذلك).
        if ($branch->departments()->exists() || DB::table('employees')->where('branch_id', $branch->id)->exists()) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => ['code' => 'BRANCH_IN_USE', 'message' => 'لا يمكن حذف فرع مرتبط بأقسام أو موظفين. عطّله بدلاً من الحذف.'],
            ], 422);
        }

        $id = $branch->id;
        $branch->delete();
        $this->recordAudit($request, 'branch_deleted', Branch::class, $id);

        return $this->ok(['message' => 'تم حذف الفرع.']);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}

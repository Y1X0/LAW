<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Http\Requests\StoreEmployeeRequest;
use Modules\HR\Http\Requests\UpdateEmployeeRequest;
use Modules\HR\Models\Employee;
use Modules\HR\Services\EmployeeService;

/**
 * إدارة الموظفين (Issue #13). محميّ بالمصادقة + صلاحيات employees.*.
 */
class EmployeeController
{
    public function __construct(private readonly EmployeeService $service) {}

    /** GET /api/employees — قائمة مع بحث وتصفية وترقيم. */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()->with(['branch:id,name', 'department:id,name']);

        if ($search = $request->query('search')) {
            // LOWER(...) LIKE: بحث غير حسّاس لحالة الأحرف عبر Postgres وsqlite معاً.
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(full_name_ar) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(full_name_en) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(employee_no) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(national_id) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle]);
            });
        }

        foreach (['branch_id', 'department_id', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $page = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Employee $e) => $this->present($e, $request))->all(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    /** POST /api/employees */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->service->create($this->guardSensitiveWrite($request, $request->validated()), $request);

        return $this->ok($this->present($employee, $request), 201);
    }

    /** GET /api/employees/{employee} */
    public function show(Request $request, Employee $employee): JsonResponse
    {
        $employee->load(['branch:id,name', 'department:id,name', 'manager:id,full_name_ar']);

        return $this->ok($this->present($employee, $request));
    }

    /** PUT /api/employees/{employee} */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->service->update($employee, $this->guardSensitiveWrite($request, $request->validated()), $request);

        return $this->ok($this->present($employee, $request));
    }

    /** DELETE /api/employees/{employee} — أرشفة (Soft Delete). */
    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $this->service->archive($employee, $request);

        return $this->ok(['message' => 'تمت أرشفة الموظف.']);
    }

    /**
     * تمثيل الموظف مع إخفاء الحقول المالية/البنكية إلا بصلاحية employees.salary.view.
     */
    private function present(Employee $employee, Request $request): array
    {
        $data = $employee->toArray();

        if (! $request->user()?->hasPermission('employees.salary.view')) {
            foreach (Employee::SENSITIVE_FIELDS as $field) {
                unset($data[$field]);
            }
        }

        return $data;
    }

    /**
     * SEC-9: يُسقط الحقول المالية/البنكية من مُدخل الكتابة إلا لمن يملك
     * employees.salary.view — تماثلاً مع إخفائها في القراءة (present)، فمن لا يراها
     * لا يضبطها. إسقاط صامت (لا 422) اتساقاً مع نمط الإخفاء.
     */
    private function guardSensitiveWrite(Request $request, array $data): array
    {
        if (! $request->user()?->hasPermission('employees.salary.view')) {
            foreach (Employee::SENSITIVE_FIELDS as $field) {
                unset($data[$field]);
            }
        }

        return $data;
    }

    private function ok(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}

<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Payroll\Models\SalaryComponent;

/**
 * كتالوج مكوّنات الراتب (Issue #33). قراءة: payroll.view · كتابة: payroll.create.
 */
class SalaryComponentController
{
    use RecordsAudit;

    public function index(Request $request): JsonResponse
    {
        $query = SalaryComponent::query();
        foreach (['type', 'value_type'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        return $this->ok($query->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $component = SalaryComponent::create($data);
        $this->recordAudit($request, 'salary_component_created', SalaryComponent::class, $component->id, [
            'code' => $component->code, 'type' => $component->type,
        ]);

        return $this->ok($component, 201);
    }

    public function update(Request $request, SalaryComponent $salary_component): JsonResponse
    {
        $data = $this->validated($request, $salary_component->id);
        $salary_component->update($data);
        $this->recordAudit($request, 'salary_component_updated', SalaryComponent::class, $salary_component->id);

        return $this->ok($salary_component);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => [$id ? 'sometimes' : 'required', 'string', 'max:80'],
            'code' => [$id ? 'sometimes' : 'required', 'string', 'max:40', Rule::unique('salary_components', 'code')->ignore($id)],
            'type' => [$id ? 'sometimes' : 'required', Rule::in(SalaryComponent::TYPES)],
            'value_type' => ['nullable', Rule::in(SalaryComponent::VALUE_TYPES)],
            'is_active' => ['boolean'],
        ]);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}

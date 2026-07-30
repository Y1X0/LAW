<?php

namespace Modules\Leave\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Leave\Models\LeaveType;

/**
 * إدارة أنواع الإجازات (Issue #17). قراءة: leaves.request · كتابة: leaves.approve.
 */
class LeaveTypeController
{
    use RecordsAudit;

    public function index(): JsonResponse
    {
        return $this->ok(LeaveType::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $type = LeaveType::create($data);
        $this->recordAudit($request, 'leave_type_created', LeaveType::class, $type->id, ['code' => $type->code]);

        return $this->ok($type, 201);
    }

    public function update(Request $request, LeaveType $leave_type): JsonResponse
    {
        $data = $this->validated($request, $leave_type->id);
        $leave_type->update($data);
        $this->recordAudit($request, 'leave_type_updated', LeaveType::class, $leave_type->id);

        return $this->ok($leave_type);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => [$id ? 'sometimes' : 'required', 'string', 'max:80'],
            'code' => [$id ? 'sometimes' : 'required', 'string', 'max:30', Rule::unique('leave_types', 'code')->ignore($id)],
            'is_paid' => ['boolean'],
            'consumes_balance' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'default_annual_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_active' => ['boolean'],
        ]);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}

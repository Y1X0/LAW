<?php

namespace Modules\Attendance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\Models\WorkShift;
use Modules\Core\Concerns\RecordsAudit;

/**
 * إدارة نوبات العمل (Issue #15). قراءة: attendance.view · كتابة: attendance.manual.
 */
class WorkShiftController
{
    use RecordsAudit;

    public function index(): JsonResponse
    {
        return $this->ok(WorkShift::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $shift = WorkShift::create($data);
        $this->recordAudit($request, 'work_shift_created', WorkShift::class, $shift->id, ['name' => $shift->name]);

        return $this->ok($shift, 201);
    }

    public function show(WorkShift $work_shift): JsonResponse
    {
        return $this->ok($work_shift);
    }

    public function update(Request $request, WorkShift $work_shift): JsonResponse
    {
        $data = $this->validated($request, false);
        $work_shift->update($data);
        $this->recordAudit($request, 'work_shift_updated', WorkShift::class, $work_shift->id, $data);

        return $this->ok($work_shift);
    }

    public function destroy(Request $request, WorkShift $work_shift): JsonResponse
    {
        $id = $work_shift->id;
        $work_shift->delete();
        $this->recordAudit($request, 'work_shift_deleted', WorkShift::class, $id);

        return $this->ok(['message' => 'تم حذف النوبة.']);
    }

    private function validated(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:60'],
            'start_time' => [$creating ? 'required' : 'sometimes', 'date_format:H:i'],
            'end_time' => [$creating ? 'required' : 'sometimes', 'date_format:H:i'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'is_active' => ['boolean'],
        ]);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}

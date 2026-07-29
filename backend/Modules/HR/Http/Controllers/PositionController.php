<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Position;

/**
 * كتالوج المسميات الوظيفية (Issue #14). قراءة: employees.view · كتابة: employees.update.
 */
class PositionController
{
    use RecordsAudit;

    public function index(Request $request): JsonResponse
    {
        $query = Position::query();
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        return $this->ok($query->orderBy('title')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $position = Position::create($data);
        $this->recordAudit($request, 'position_created', Position::class, $position->id, ['title' => $position->title]);

        return $this->ok($position, 201);
    }

    public function show(Position $position): JsonResponse
    {
        return $this->ok($position);
    }

    public function update(Request $request, Position $position): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $position->update($data);
        $this->recordAudit($request, 'position_updated', Position::class, $position->id, $data);

        return $this->ok($position);
    }

    public function destroy(Request $request, Position $position): JsonResponse
    {
        $id = $position->id;
        $position->delete();
        $this->recordAudit($request, 'position_deleted', Position::class, $id);

        return $this->ok(['message' => 'تم حذف المسمّى الوظيفي.']);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}

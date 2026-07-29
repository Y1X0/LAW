<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeDocument;

/**
 * مستندات الموظف (Issue #14). قراءة: employees.view · كتابة: employees.update.
 */
class EmployeeDocumentController
{
    use RecordsAudit;

    public function index(Employee $employee): JsonResponse
    {
        return $this->ok($employee->documents()->orderByDesc('id')->get());
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'doc_type' => ['required', Rule::in(EmployeeDocument::DOC_TYPES)],
            'title' => ['required', 'string', 'max:200'],
            'file_path' => ['required', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $data['employee_id'] = $employee->id;
        $data['uploaded_by'] = $request->user()?->id;
        $document = EmployeeDocument::create($data);

        $this->recordAudit($request, 'employee_document_added', Employee::class, $employee->id, [
            'document_id' => $document->id, 'doc_type' => $document->doc_type,
        ]);

        return $this->ok($document, 201);
    }

    public function destroy(Request $request, Employee $employee, EmployeeDocument $document): JsonResponse
    {
        abort_unless($document->employee_id === $employee->id, 404);

        $id = $document->id;
        $document->delete(); // أرشفة (Soft Delete)
        $this->recordAudit($request, 'employee_document_removed', Employee::class, $employee->id, ['document_id' => $id]);

        return $this->ok(['message' => 'تمت أرشفة المستند.']);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}

<?php

namespace Modules\DataTransfer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\DataTransfer\Services\DataImportService;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\Client;

/**
 * استيراد بيانات رئيسية من Excel (الموظفون + العملاء). preview: تحقّق بلا حفظ.
 * commit: حفظ ذرّي (الكل-أو-لا-شيء). محميّ بصلاحية إنشاء الكيان في routes/api.php.
 */
class DataImportController
{
    use RecordsAudit;

    public function __construct(private readonly DataImportService $import) {}

    public function previewEmployees(Request $request): JsonResponse
    {
        $rows = $this->rowsFromRequest($request);
        $canSetSalary = $request->user()?->hasPermission('employees.salary.view') ?? false;

        return $this->ok($this->import->previewEmployees($rows, $canSetSalary));
    }

    public function commitEmployees(Request $request): JsonResponse
    {
        $rows = $this->rowsFromRequest($request);
        $canSetSalary = $request->user()?->hasPermission('employees.salary.view') ?? false;

        $result = $this->import->commitEmployees($rows, $canSetSalary, $request->user()?->id);

        if ($result['errors'] !== []) {
            return response()->json([
                'data' => null, 'meta' => null,
                'errors' => ['code' => 'IMPORT_VALIDATION', 'message' => 'يوجد صفوف غير صحيحة — لم يُحفَظ شيء.', 'rows' => $result['errors']],
            ], 422);
        }

        $this->recordAudit($request, 'employees_imported', Employee::class, 0, [
            'created' => $result['created'], 'updated' => $result['updated'],
        ]);

        return $this->ok(['created' => $result['created'], 'updated' => $result['updated']]);
    }

    public function previewClients(Request $request): JsonResponse
    {
        return $this->ok($this->import->previewClients($this->rowsFromRequest($request)));
    }

    public function commitClients(Request $request): JsonResponse
    {
        $result = $this->import->commitClients($this->rowsFromRequest($request), $request);

        if ($result['errors'] !== []) {
            return response()->json([
                'data' => null, 'meta' => null,
                'errors' => ['code' => 'IMPORT_VALIDATION', 'message' => 'يوجد صفوف غير صحيحة — لم يُحفَظ شيء.', 'rows' => $result['errors']],
            ], 422);
        }

        $this->recordAudit($request, 'clients_imported', Client::class, 0, [
            'created' => $result['created'], 'updated' => $result['updated'],
        ]);

        return $this->ok(['created' => $result['created'], 'updated' => $result['updated']]);
    }

    /**
     * يتحقّق من الملف المرفوع ويحوّله إلى صفوف. التحقّق قائم على المحتوى (يحاول القراءة)
     * بدل mimes — أكثر موثوقية لملفات xlsx (تُخمَّن كـ zip). ملف غير قابل للقراءة ⇒ 422.
     */
    private function rowsFromRequest(Request $request): array
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        try {
            return $this->import->parse($request->file('file')->getRealPath());
        } catch (\Throwable) {
            abort(422, 'الملف غير قابل للقراءة كملف Excel صالح.');
        }
    }

    private function ok(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}

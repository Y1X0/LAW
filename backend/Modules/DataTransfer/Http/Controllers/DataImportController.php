<?php

namespace Modules\DataTransfer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\DataTransfer\Services\DataImportService;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\LegalCase;

/**
 * استيراد بيانات رئيسية من Excel (الموظفون + العملاء + القضايا). preview: تحقّق بلا حفظ.
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
        $raw = $this->rowsFromRequest($request);
        $detected = $raw === [] ? [] : array_keys($raw[0]);
        $mapping = $this->clientMapping($request);
        $rows = $mapping !== null ? $this->import->applyMapping($raw, $mapping) : $raw;

        $summary = $this->import->previewClients($rows, $this->clientMatchKey($request));

        // بيانات وصفية لواجهة مطابقة الأعمدة: الحقول (وإلزاميّتها) ورؤوس الملف المكتشَفة.
        return $this->ok($summary + [
            'fields' => array_map(
                fn (string $key) => ['key' => $key, 'required' => in_array($key, DataImportService::CLIENT_REQUIRED, true)],
                DataImportService::CLIENT_FIELDS,
            ),
            'match_keys' => DataImportService::CLIENT_MATCH_KEYS,
            'detected_headers' => $detected,
        ]);
    }

    public function commitClients(Request $request): JsonResponse
    {
        $raw = $this->rowsFromRequest($request);
        $mapping = $this->clientMapping($request);
        $rows = $mapping !== null ? $this->import->applyMapping($raw, $mapping) : $raw;

        $result = $this->import->commitClients($rows, $request, $this->clientMatchKey($request));

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

    public function previewCases(Request $request): JsonResponse
    {
        $raw = $this->rowsFromRequest($request);
        $detected = $raw === [] ? [] : array_keys($raw[0]);
        $mapping = $this->caseMapping($request);
        $rows = $mapping !== null ? $this->import->applyMapping($raw, $mapping) : $raw;

        $summary = $this->import->previewCases($rows);

        // بيانات وصفية لواجهة مطابقة الأعمدة: الحقول (وإلزاميّتها) ورؤوس الملف ومفتاح المطابقة الثابت.
        return $this->ok($summary + [
            'fields' => array_map(
                fn (string $key) => ['key' => $key, 'required' => in_array($key, DataImportService::CASE_REQUIRED, true)],
                DataImportService::CASE_FIELDS,
            ),
            'match_keys' => [DataImportService::CASE_MATCH_KEY],
            'detected_headers' => $detected,
        ]);
    }

    public function commitCases(Request $request): JsonResponse
    {
        $raw = $this->rowsFromRequest($request);
        $mapping = $this->caseMapping($request);
        $rows = $mapping !== null ? $this->import->applyMapping($raw, $mapping) : $raw;

        $result = $this->import->commitCases($rows, $request);

        if ($result['errors'] !== []) {
            return response()->json([
                'data' => null, 'meta' => null,
                'errors' => ['code' => 'IMPORT_VALIDATION', 'message' => 'يوجد صفوف غير صحيحة — لم يُحفَظ شيء.', 'rows' => $result['errors']],
            ], 422);
        }

        $this->recordAudit($request, 'cases_imported', LegalCase::class, 0, [
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

    /**
     * مواصفة مطابقة الأعمدة (field => header) من الطلب — تُنظَّف: تُقبَل حقول العميل المعروفة
     * فقط ورؤوس نصّية غير فارغة. غيابها ⇒ null (تُعامَل الرؤوس كأسماء حقول مباشرة — توافق خلفي).
     */
    private function clientMapping(Request $request): ?array
    {
        $mapping = $request->input('mapping');
        if (! is_array($mapping)) {
            return null;
        }

        $clean = [];
        foreach ($mapping as $field => $header) {
            if (in_array($field, DataImportService::CLIENT_FIELDS, true) && is_string($header) && $header !== '') {
                $clean[$field] = $header;
            }
        }

        return $clean !== [] ? $clean : null;
    }

    /** مفتاح المطابقة المختار (upsert) — ضمن قائمة السماح، وإلّا الافتراضي national_id. */
    private function clientMatchKey(Request $request): string
    {
        $key = (string) $request->input('match_key', 'national_id');

        return in_array($key, DataImportService::CLIENT_MATCH_KEYS, true) ? $key : 'national_id';
    }

    /** مواصفة مطابقة أعمدة القضية (field => header) — تُقبَل حقول القضية المعروفة فقط. */
    private function caseMapping(Request $request): ?array
    {
        $mapping = $request->input('mapping');
        if (! is_array($mapping)) {
            return null;
        }

        $clean = [];
        foreach ($mapping as $field => $header) {
            if (in_array($field, DataImportService::CASE_FIELDS, true) && is_string($header) && $header !== '') {
                $clean[$field] = $header;
            }
        }

        return $clean !== [] ? $clean : null;
    }

    private function ok(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}

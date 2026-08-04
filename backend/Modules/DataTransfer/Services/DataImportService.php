<?php

namespace Modules\DataTransfer\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;
use Modules\Legal\Http\Requests\StoreClientRequest;
use Modules\Legal\Models\Client;
use Modules\Legal\Services\ClientService;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * استيراد بيانات رئيسية من Excel. يدعم الموظفين (مفتاح طبيعي employee_no) والعملاء
 * (مفتاح national_id عند وجوده). التحقّق يتم لكل الصفوف أولاً؛ الحفظ ذرّي (الكل-أو-لا-شيء)
 * عبر DB::transaction. استيراد العملاء يمرّ عبر ClientService (لا يتجاوز منطق النطاق —
 * تدقيق لكل صف). البيانات المشتقّة (حضور/إجازات/رواتب) تُستثنى.
 */
class DataImportService
{
    /** حقول استيراد العميل القابلة للمطابقة (تُغذّي واجهة مطابقة الأعمدة). */
    public const CLIENT_FIELDS = ['name', 'type', 'phone', 'email', 'national_id', 'status'];

    /** الحقول الإلزامية للعميل. */
    public const CLIENT_REQUIRED = ['name', 'type'];

    /** مفاتيح المطابقة المسموحة للترقية (upsert). */
    public const CLIENT_MATCH_KEYS = ['national_id', 'email', 'name'];

    /**
     * يعيد تسمية صفوف مُفتاحة برؤوس الملف إلى صفوف مُفتاحة بحقول الكيان، وفق مواصفة
     * المطابقة (field => header). حقل بلا رأس مُطابِق ⇒ null (يلتقطه التحقّق لاحقاً).
     */
    public function applyMapping(array $rows, array $mapping): array
    {
        return array_map(function (array $row) use ($mapping) {
            $out = [];
            foreach ($mapping as $field => $header) {
                $out[$field] = ($header !== null && $header !== '') ? ($row[$header] ?? null) : null;
            }

            return $out;
        }, $rows);
    }

    /** يقرأ ملف xlsx إلى صفوف ترابطية (رأس ⇒ قيمة)، متجاهلاً الصفوف الفارغة. */
    public function parse(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $matrix = $sheet->toArray(null, true, false);
        $headers = array_map(fn ($h) => is_string($h) ? trim($h) : $h, array_shift($matrix) ?? []);

        $rows = [];
        foreach ($matrix as $raw) {
            if (count(array_filter($raw, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue; // صف فارغ
            }
            $rows[] = array_combine($headers, array_pad(array_slice($raw, 0, count($headers)), count($headers), null));
        }

        return $rows;
    }

    /** معاينة (dry-run): يعيد ملخّصاً وأخطاء الصفوف دون أي حفظ. */
    public function previewEmployees(array $rows, bool $canSetSalary): array
    {
        $create = 0;
        $update = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $result = $this->resolveEmployeeRow($row, $canSetSalary);
            if (isset($result['error'])) {
                $errors[] = ['row' => $i + 2, 'message' => $result['error']]; // +2: رأس + فهرس صفري
            } elseif ($result['action'] === 'create') {
                $create++;
            } else {
                $update++;
            }
        }

        return [
            'total' => count($rows),
            'create' => $create,
            'update' => $update,
            'invalid' => count($errors),
            'errors' => array_slice($errors, 0, 100),
        ];
    }

    /**
     * تطبيق الاستيراد ذرّياً. يتحقّق من كل الصفوف أولاً؛ إن وُجد أي خطأ لا يُحفَظ شيء.
     *
     * @return array{created:int,updated:int}
     */
    public function commitEmployees(array $rows, bool $canSetSalary, ?int $actorId): array
    {
        $resolved = [];
        $errors = [];
        foreach ($rows as $i => $row) {
            $result = $this->resolveEmployeeRow($row, $canSetSalary);
            if (isset($result['error'])) {
                $errors[] = ['row' => $i + 2, 'message' => $result['error']];
            } else {
                $resolved[] = $result;
            }
        }

        if ($errors !== []) {
            return ['created' => 0, 'updated' => 0, 'errors' => $errors];
        }

        $created = 0;
        $updated = 0;
        DB::transaction(function () use ($resolved, $actorId, &$created, &$updated) {
            foreach ($resolved as $r) {
                $attrs = $r['attributes'];
                if ($r['action'] === 'create') {
                    $attrs['created_by'] = $actorId;
                    Employee::create($attrs);
                    $created++;
                } else {
                    $attrs['updated_by'] = $actorId;
                    Employee::where('employee_no', $r['key'])->first()?->update($attrs);
                    $updated++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated, 'errors' => []];
    }

    /** معاينة استيراد العملاء (dry-run): ملخّص وأخطاء الصفوف بلا حفظ. */
    public function previewClients(array $rows, string $matchKey = 'national_id'): array
    {
        $create = 0;
        $update = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $result = $this->resolveClientRow($row, $matchKey);
            if (isset($result['error'])) {
                $errors[] = ['row' => $i + 2, 'message' => $result['error']];
            } elseif ($result['action'] === 'create') {
                $create++;
            } else {
                $update++;
            }
        }

        return [
            'total' => count($rows),
            'create' => $create,
            'update' => $update,
            'invalid' => count($errors),
            'errors' => array_slice($errors, 0, 100),
        ];
    }

    /**
     * تطبيق استيراد العملاء ذرّياً عبر ClientService (تدقيق + created_by لكل صف).
     * يتحقّق من كل الصفوف أولاً؛ إن وُجد أي خطأ لا يُحفَظ شيء.
     *
     * @return array{created:int,updated:int,errors:array}
     */
    public function commitClients(array $rows, Request $request, string $matchKey = 'national_id'): array
    {
        $resolved = [];
        $errors = [];
        foreach ($rows as $i => $row) {
            $result = $this->resolveClientRow($row, $matchKey);
            if (isset($result['error'])) {
                $errors[] = ['row' => $i + 2, 'message' => $result['error']];
            } else {
                $resolved[] = $result;
            }
        }

        if ($errors !== []) {
            return ['created' => 0, 'updated' => 0, 'errors' => $errors];
        }

        $service = app(ClientService::class);
        $created = 0;
        $updated = 0;
        DB::transaction(function () use ($resolved, $request, $service, $matchKey, &$created, &$updated) {
            foreach ($resolved as $r) {
                $existing = $r['action'] === 'update' ? Client::where($matchKey, $r['key'])->first() : null;
                if ($existing !== null) {
                    $service->update($existing, $r['data'], $request);
                    $updated++;
                } else {
                    $service->create($r['data'], $request);
                    $created++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated, 'errors' => []];
    }

    /**
     * يتحقّق ويحوّل صفّ عميل عبر قواعد StoreClientRequest (+ الحالة). الترقية (upsert) بمفتاح
     * المطابقة المختار عند وجود قيمته، وإلّا إنشاء. يعيد ['error'] أو ['action','key','data'].
     */
    private function resolveClientRow(array $row, string $matchKey): array
    {
        $data = [
            'name' => $this->str($row['name'] ?? null),
            'type' => $this->str($row['type'] ?? null),
            'phone' => $this->str($row['phone'] ?? null) ?: null,
            'email' => $this->str($row['email'] ?? null) ?: null,
            'national_id' => $this->str($row['national_id'] ?? null) ?: null,
            'status' => $this->str($row['status'] ?? null) ?: 'active',
        ];

        $validator = Validator::make($data, (new StoreClientRequest)->rules() + [
            'status' => ['in:'.implode(',', Client::STATUSES)],
        ]);
        if ($validator->fails()) {
            return ['error' => $validator->errors()->first()];
        }

        $key = $data[$matchKey] ?? null;
        $existing = $key !== null && $key !== '' && Client::where($matchKey, $key)->exists();

        return ['action' => $existing ? 'update' : 'create', 'key' => $key, 'data' => $data];
    }

    /** يتحقّق ويحوّل صفّ موظف؛ يعيد ['error'=>..] أو ['action','key','attributes']. */
    private function resolveEmployeeRow(array $row, bool $canSetSalary): array
    {
        $employeeNo = $this->str($row['employee_no'] ?? null);
        $fullName = $this->str($row['full_name_ar'] ?? null);
        $nationalId = $this->str($row['national_id'] ?? null);
        $branchName = $this->str($row['branch'] ?? null);
        $deptName = $this->str($row['department'] ?? null);

        if ($employeeNo === '') {
            return ['error' => 'employee_no مطلوب'];
        }
        if ($fullName === '') {
            return ['error' => 'full_name_ar مطلوب'];
        }
        if ($nationalId === '') {
            return ['error' => 'national_id مطلوب'];
        }

        $branch = $branchName !== '' ? Branch::where('name', $branchName)->first() : null;
        if ($branch === null) {
            return ['error' => "الفرع غير موجود: {$branchName}"];
        }
        $dept = $deptName !== '' ? Department::where('name', $deptName)->where('branch_id', $branch->id)->first() : null;
        if ($dept === null) {
            return ['error' => "القسم غير موجود ضمن الفرع: {$deptName}"];
        }

        $status = $this->str($row['status'] ?? null) ?: 'active';
        if (! in_array($status, Employee::STATUSES, true)) {
            return ['error' => "حالة غير صحيحة: {$status}"];
        }

        $existing = Employee::where('employee_no', $employeeNo)->first();
        $nationalConflict = Employee::where('national_id', $nationalId)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();
        if ($nationalConflict) {
            return ['error' => "national_id مستخدم لموظف آخر: {$nationalId}"];
        }

        $attrs = [
            'employee_no' => $employeeNo,
            'full_name_ar' => $fullName,
            'full_name_en' => $this->str($row['full_name_en'] ?? null) ?: null,
            'national_id' => $nationalId,
            'branch_id' => $branch->id,
            'department_id' => $dept->id,
            'job_title' => $this->str($row['job_title'] ?? null) ?: null,
            'status' => $status,
            'hire_date' => $this->date($row['hire_date'] ?? null),
        ];
        // الحقول المالية تُطبَّق فقط لمن يملك صلاحية عرض الراتب (اتساقاً مع SEC-9).
        if ($canSetSalary && isset($row['basic_salary']) && $this->str($row['basic_salary']) !== '') {
            $attrs['basic_salary'] = (float) $row['basic_salary'];
        }
        if ($canSetSalary && isset($row['bank_name'])) {
            $attrs['bank_name'] = $this->str($row['bank_name']) ?: null;
        }
        if ($canSetSalary && isset($row['bank_account'])) {
            $attrs['bank_account'] = $this->str($row['bank_account']) ?: null;
        }

        return ['action' => $existing ? 'update' : 'create', 'key' => $employeeNo, 'attributes' => $attrs];
    }

    private function str(mixed $v): string
    {
        return trim((string) ($v ?? ''));
    }

    private function date(mixed $v): ?string
    {
        $s = $this->str($v);
        if ($s === '') {
            return null;
        }

        try {
            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}

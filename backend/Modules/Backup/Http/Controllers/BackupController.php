<?php

namespace Modules\Backup\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Backup\Models\Backup;
use Modules\Backup\Services\BackupService;
use Modules\Core\Concerns\RecordsAudit;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * إدارة النسخ الاحتياطية لـ Owner (Phase 13 · PR-2). محميّ بصلاحية backup.manage: عرض القائمة،
 * إنشاء نسخة الآن، وتنزيل نسخة. لا استعادة هنا (تُجرى عبر CLI مُحصَّن + runbook — قرار أمان).
 */
class BackupController
{
    use RecordsAudit;

    public function __construct(private readonly BackupService $service) {}

    public function index(): JsonResponse
    {
        $rows = Backup::with('creator:id,name')->orderByDesc('id')->limit(100)->get()
            ->map(fn (Backup $b) => $this->present($b))->all();

        return $this->ok($rows);
    }

    /** ينشئ نسخة يدوية الآن (Owner). التفريغ الفعلي عبر BackupService (تدقيق + تقليم). */
    public function store(Request $request): JsonResponse
    {
        try {
            $backup = $this->service->run('manual', 'manual', $request->user()?->id, $request);
        } catch (\Throwable $e) {
            // فشل التفريغ/الرفع سُجِّل بالفعل (صفّ failed + تدقيق) مع تفاصيله التقنية.
            // نُعيد للواجهة رسالة عربية واضحة بلا تسريب أوامر pg_dump أو بيانات التخزين.
            report($e);

            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => [
                    'code' => 'BACKUP_FAILED',
                    'message' => 'تعذّر إنشاء النسخة الاحتياطية. تم تسجيل العطل — يرجى المحاولة لاحقاً أو مراجعة سجلّات النظام.',
                ],
            ], 500);
        }

        return $this->ok($this->present($backup), 201);
    }

    /** تنزيل ملف النسخة (مصادَق + backup.manage + تدقيق). غير المكتملة ⇒ 404. */
    public function download(Request $request, Backup $backup): StreamedResponse
    {
        abort_if($backup->status !== 'completed' || ! $backup->path, 404, 'النسخة غير متاحة للتنزيل.');

        $this->recordAudit($request, 'backup_downloaded', Backup::class, $backup->id, ['filename' => $backup->filename]);

        return Storage::disk($backup->disk)->download($backup->path, $backup->filename);
    }

    private function present(Backup $backup): array
    {
        return [
            'id' => $backup->id,
            'filename' => $backup->filename,
            'kind' => $backup->kind,
            'status' => $backup->status,
            'trigger' => $backup->trigger,
            'size_bytes' => $backup->size_bytes,
            'created_by' => $backup->creator?->name,
            'created_at' => $backup->created_at?->toIso8601String(),
        ];
    }

    private function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}

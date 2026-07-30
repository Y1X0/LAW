<?php

namespace Modules\Payroll\Services;

use Illuminate\Http\Request;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Payroll\Models\PayrollItem;
use Modules\Payroll\Models\PayrollRun;

/**
 * دورة اعتماد وقفل مسير الرواتب (Issue #37).
 *
 * draft/processing (بعد الحساب #36) → approved → locked. بعد القفل تصبح الأرقام
 * غير قابلة للتغيير (الحساب/اللقطات مرفوضة أصلاً لغير المسودة). لا يمسّ منطق الحساب.
 */
class PayrollApprovalService
{
    use RecordsAudit;

    /** اعتماد المسير (يشترط أن يكون محسوباً: توجد نتائج payroll_items). */
    public function approve(PayrollRun $run, Request $request): PayrollRun
    {
        abort_unless(
            in_array($run->status, PayrollRun::MUTABLE_STATUSES, true),
            422,
            'حالة المسير لا تسمح بالاعتماد.'
        );
        abort_unless(
            PayrollItem::where('payroll_run_id', $run->id)->exists(),
            422,
            'لا يمكن اعتماد مسير قبل حسابه.'
        );

        $run->update([
            'status' => 'approved',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        $this->recordAudit($request, 'payroll_run_approved', PayrollRun::class, $run->id, [
            'payroll_period_id' => $run->payroll_period_id,
        ]);

        return $run;
    }

    /** قفل المسير المعتمد (يصبح نهائياً غير قابل للتغيير). */
    public function lock(PayrollRun $run, Request $request): PayrollRun
    {
        abort_unless($run->status === 'approved', 422, 'يجب اعتماد المسير قبل قفله.');

        $run->update([
            'status' => 'locked',
            'locked_by' => $request->user()?->id,
            'locked_at' => now(),
        ]);

        $this->recordAudit($request, 'payroll_run_locked', PayrollRun::class, $run->id, [
            'payroll_period_id' => $run->payroll_period_id,
        ]);

        return $run;
    }
}

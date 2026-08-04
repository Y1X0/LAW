<?php

namespace Modules\Notifications\Services;

use Modules\Finance\Models\Invoice;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;
use Modules\Notifications\Concerns\NotifiesUsers;
use Modules\Notifications\Models\Notification;

/**
 * محرّك التذكيرات الزمنية (Notifications / Phase 8 · PR-4) — قراءة فقط.
 *
 * يُشغَّل عبر الأمر notifications:remind (ولاحقاً schedule:run على Render cron). يكتشف
 * الأحداث الزمنية ويُطلق تذكيراً واحداً لكل (مستخدم/نوع/كيان) — منع التكرار عبر فحص وجود
 * صفّ إشعار مطابق مسبقاً، فتشغيله مرّاتٍ لا يُنتج تكراراً. لا يغيّر أي حالة (خاصّةً حالة
 * الفاتورة overdue التي لا منطق انتقال لها — نكتشف التأخّر بالتاريخ ونُشعر فقط).
 */
class ReminderService
{
    use NotifiesUsers;

    /** نافذة تذكير الجلسات القادمة (أيام). */
    private const HEARING_WINDOW_DAYS = 3;

    /** نافذة تذكير الفواتير المقتربة من الاستحقاق (أيام). */
    private const INVOICE_DUE_WINDOW_DAYS = 3;

    /** الفواتير المُصدَرة غير المسدّدة بالكامل (مصدر تذكير الاستحقاق). */
    private const ISSUED = [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL];

    /** يشغّل كل مصادر التذكير ويعيد عدد التذكيرات المُطلَقة لكل مصدر. */
    public function run(): array
    {
        return [
            'hearings' => $this->hearingReminders(),
            'invoices_due' => $this->invoiceDueSoonReminders(),
            'invoices_overdue' => $this->invoiceOverdueReminders(),
        ];
    }

    /** جلسات قادمة ضمن النافذة → المحامون المسؤول/المسنَدون للقضية. */
    private function hearingReminders(): int
    {
        $sent = 0;
        $hearings = Hearing::query()
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [now(), now()->addDays(self::HEARING_WINDOW_DAYS)])
            ->with('case.assignments')
            ->get();

        foreach ($hearings as $hearing) {
            $case = $hearing->case;
            if ($case === null) {
                continue;
            }

            $title = 'جلسة قادمة';
            $body = "جلسة في قضية «{$case->title}» بتاريخ {$hearing->scheduled_at->format('Y-m-d H:i')}.";
            foreach ($this->caseLawyerUserIds($case) as $userId) {
                $sent += $this->remindOnce($userId, 'hearing_upcoming', $title, $body, 'Hearing', $hearing->id);
            }
        }

        return $sent;
    }

    /** فواتير يقترب استحقاقها (ضمن النافذة، لم يفُت بعد) → معتمِد الفاتورة. */
    private function invoiceDueSoonReminders(): int
    {
        $sent = 0;
        $invoices = $this->outstandingInvoices()
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays(self::INVOICE_DUE_WINDOW_DAYS)->toDateString())
            ->get();

        foreach ($invoices as $invoice) {
            $body = "الفاتورة {$invoice->invoice_no} تستحقّ في {$invoice->due_date->toDateString()} (المتبقّي {$invoice->balance}).";
            $sent += $this->remindOnce($invoice->approved_by, 'invoice_due_soon', 'فاتورة تقترب من الاستحقاق', $body, 'Invoice', $invoice->id);
        }

        return $sent;
    }

    /** فواتير تجاوزت الاستحقاق بالتاريخ (بلا تغيير حالة) → معتمِد الفاتورة. */
    private function invoiceOverdueReminders(): int
    {
        $sent = 0;
        $invoices = $this->outstandingInvoices()
            ->whereDate('due_date', '<', now()->toDateString())
            ->get();

        foreach ($invoices as $invoice) {
            $body = "الفاتورة {$invoice->invoice_no} تجاوزت موعد استحقاقها ({$invoice->due_date->toDateString()})، المتبقّي {$invoice->balance}.";
            $sent += $this->remindOnce($invoice->approved_by, 'invoice_overdue', 'فاتورة متأخّرة عن الاستحقاق', $body, 'Invoice', $invoice->id);
        }

        return $sent;
    }

    /** أساس استعلام الفواتير المستحقّة: مُصدَرة، لها معتمِد وتاريخ استحقاق، ومتبقٍّ > 0. */
    private function outstandingInvoices()
    {
        return Invoice::query()
            ->whereIn('status', self::ISSUED)
            ->where('balance', '>', 0)
            ->whereNotNull('due_date')
            ->whereNotNull('approved_by');
    }

    /** حسابات المحامين (المسؤول + المسنَدين) المرتبطة بالقضية — بلا تكرار، وبلا من لا حساب له. */
    private function caseLawyerUserIds(LegalCase $case): array
    {
        $employeeIds = $case->assignments->pluck('employee_id')
            ->push($case->responsible_lawyer_id)
            ->filter()
            ->unique()
            ->all();

        if ($employeeIds === []) {
            return [];
        }

        return Employee::query()
            ->whereIn('id', $employeeIds)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->all();
    }

    /** يُطلق تذكيراً ما لم يُطلَق مثله سابقاً (منع تكرار)؛ يعيد 1 عند الإطلاق و0 عند التخطّي. */
    private function remindOnce(?int $userId, string $type, string $title, string $body, string $relatedType, int $relatedId): int
    {
        if ($userId === null || $this->alreadySent($userId, $type, $relatedType, $relatedId)) {
            return 0;
        }

        $this->notify($userId, $type, $title, $body, $relatedType, $relatedId);

        return 1;
    }

    private function alreadySent(int $userId, string $type, string $relatedType, int $relatedId): bool
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('related_type', $relatedType)
            ->where('related_id', $relatedId)
            ->exists();
    }
}

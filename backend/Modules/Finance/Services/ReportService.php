<?php

namespace Modules\Finance\Services;

use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\JournalLine;

/**
 * تقارير مالية للقراءة فقط (Finance / Phase 6 · PR-10).
 *
 * كل المجاميع تُحسب في قاعدة البيانات من القيود المُرحَّلة والفواتير — لا منطق محاسبي
 * جديد، ولا حساب في الواجهة. ثلاثة تقارير: ميزان المراجعة، المستحقات، والأرباح/الخسائر.
 */
class ReportService
{
    /** الفواتير المُصدَرة (مصدر المستحقات). */
    private const ISSUED = [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID];

    /** ميزان المراجعة: مدين/دائن لكل حساب دفتر من القيود المُرحَّلة ضمن المدى. */
    public function trialBalance(?string $from, ?string $to): array
    {
        $rows = $this->postedLines($from, $to)
            ->groupBy('coa.id', 'coa.code', 'coa.name', 'coa.type')
            ->orderBy('coa.code')
            ->selectRaw('coa.id as account_id, coa.code, coa.name, coa.type, SUM(journal_lines.debit) as debit, SUM(journal_lines.credit) as credit')
            ->get();

        return [
            'accounts' => $rows->map(fn ($r) => [
                'account_id' => (int) $r->account_id,
                'code' => $r->code,
                'name' => $r->name,
                'type' => $r->type,
                'debit' => $this->money($r->debit),
                'credit' => $this->money($r->credit),
            ])->all(),
            'total_debit' => $this->money($rows->sum(fn ($r) => (float) $r->debit)),
            'total_credit' => $this->money($rows->sum(fn ($r) => (float) $r->credit)),
        ];
    }

    /** المستحقات: المتبقّي لكل عميل من الفواتير المُصدَرة (المتبقّي > 0 فقط). */
    public function receivables(): array
    {
        $rows = Invoice::query()
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->whereIn('invoices.status', self::ISSUED)
            ->groupBy('clients.id', 'clients.name')
            ->havingRaw('SUM(invoices.balance) > 0')
            ->orderByRaw('SUM(invoices.balance) DESC')
            ->selectRaw('clients.id as client_id, clients.name as client_name, SUM(invoices.total) as invoiced, SUM(invoices.paid_amount) as paid, SUM(invoices.balance) as outstanding')
            ->get();

        return [
            'clients' => $rows->map(fn ($r) => [
                'client_id' => (int) $r->client_id,
                'client_name' => $r->client_name,
                'invoiced' => $this->money($r->invoiced),
                'paid' => $this->money($r->paid),
                'outstanding' => $this->money($r->outstanding),
            ])->all(),
            'total_outstanding' => $this->money($rows->sum(fn ($r) => (float) $r->outstanding)),
        ];
    }

    /** الأرباح/الخسائر: الإيرادات − المصروفات ضمن المدى (من حسابات النوعين). */
    public function incomeExpense(?string $from, ?string $to): array
    {
        $rows = $this->postedLines($from, $to)
            ->whereIn('coa.type', ['revenue', 'expense'])
            ->groupBy('coa.id', 'coa.code', 'coa.name', 'coa.type')
            ->orderBy('coa.code')
            ->selectRaw('coa.id as account_id, coa.code, coa.name, coa.type, SUM(journal_lines.debit) as debit, SUM(journal_lines.credit) as credit')
            ->get();

        $revenueAccounts = [];
        $expenseAccounts = [];
        $revenue = 0.0;
        $expenses = 0.0;

        foreach ($rows as $r) {
            if ($r->type === 'revenue') {
                $net = (float) $r->credit - (float) $r->debit; // الإيراد دائن بطبيعته
                $revenue += $net;
                $revenueAccounts[] = ['code' => $r->code, 'name' => $r->name, 'amount' => $this->money($net)];
            } else {
                $net = (float) $r->debit - (float) $r->credit; // المصروف مدين بطبيعته
                $expenses += $net;
                $expenseAccounts[] = ['code' => $r->code, 'name' => $r->name, 'amount' => $this->money($net)];
            }
        }

        return [
            'revenue' => $this->money($revenue),
            'expenses' => $this->money($expenses),
            'net' => $this->money($revenue - $expenses),
            'revenue_accounts' => $revenueAccounts,
            'expense_accounts' => $expenseAccounts,
        ];
    }

    /** أساس الاستعلام: سطور القيود المُرحَّلة مربوطة بدليل الحسابات ضمن المدى. */
    private function postedLines(?string $from, ?string $to)
    {
        return JournalLine::query()
            ->join('journal_entries as je', 'je.id', '=', 'journal_lines.journal_entry_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'journal_lines.account_id')
            ->where('je.posted', true)
            ->when($from, fn ($q) => $q->whereDate('je.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('je.entry_date', '<=', $to));
    }

    private function money(float|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}

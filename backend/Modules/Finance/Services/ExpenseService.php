<?php

namespace Modules\Finance\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Finance\Models\Expense;
use Modules\Finance\Models\FinancialAccount;
use Modules\Finance\Support\AccountResolver;
use Modules\Finance\Support\AccountRole;

/**
 * منطق أعمال سندات الصرف (Finance / Phase 6 · PR-8).
 *
 * يضمن:
 *  - القيد الصحيح: مدين حساب المصروف (GENERAL_EXPENSE بالدور) = دائن الصندوق/البنك الدافع
 *    (بحسب نوعه)، بالكامل عبر JournalService + AccountResolver — لا كتابة قيود مباشرة.
 *  - لا حذف: التصحيح بعكس ينشئ سنداً بمبلغ سالب ويعكس القيد؛ الأصل وقيده يبقيان تاريخيّاً،
 *    ويُمنع عكس السند مرّتين.
 *  - رصيد الحساب الدافع يُنقص عند الصرف ويُعاد عند العكس.
 *
 * التصنيف والقضية يُخزَّنان على الصف لتقارير الأرباح/الخسائر والمصروفات حسب التصنيف/القضية.
 */
class ExpenseService
{
    use RecordsAudit;

    public function __construct(
        private readonly JournalService $journal,
        private readonly AccountResolver $accounts,
    ) {}

    public function record(array $data, Request $request): Expense
    {
        $amount = round((float) $data['amount'], 2);
        $account = FinancialAccount::findOrFail($data['account_id']);

        return DB::transaction(function () use ($data, $amount, $account, $request): Expense {
            $expense = Expense::create([
                'voucher_no' => null,
                'category_id' => $data['category_id'],
                'case_id' => $data['case_id'] ?? null,
                'amount' => $amount,
                'method' => $data['method'],
                'account_id' => $account->id,
                'beneficiary' => $data['beneficiary'] ?? null,
                'expense_date' => $data['expense_date'] ?? now()->toDateString(),
                'description' => $data['description'] ?? null,
                'paid_by' => $request->user()?->id,
            ]);

            $entry = $this->postExpenseJournal($expense, $account, $amount, $request);

            $expense->forceFill([
                'voucher_no' => $this->formatNo($expense->id),
                'journal_entry_id' => $entry->id,
            ])->save();

            $account->decrement('current_balance', $amount);

            $this->recordAudit($request, 'expense_recorded', Expense::class, $expense->id, [
                'voucher_no' => $expense->voucher_no,
                'amount' => $amount,
                'case_id' => $expense->case_id,
            ]);

            return $expense;
        });
    }

    /** عكس سند صرف: ينشئ سنداً سالباً ويعكس قيده، ويترك الأصل كما هو. */
    public function reverse(Expense $expense, Request $request): Expense
    {
        if ((float) $expense->amount <= 0) {
            throw ValidationException::withMessages(['expense' => 'لا يمكن عكس سند عكسي.']);
        }
        if ($expense->isReversed()) {
            throw ValidationException::withMessages(['expense' => 'السند مُعكوس مسبقاً.']);
        }
        if ($expense->journal_entry_id === null) {
            throw ValidationException::withMessages(['expense' => 'السند بلا قيد لعكسه.']);
        }

        return DB::transaction(function () use ($expense, $request): Expense {
            $reversalEntry = $this->journal->reverse($expense->journalEntry, $request, 'عكس سند صرف '.$expense->voucher_no);

            $reversal = Expense::create([
                'voucher_no' => null,
                'category_id' => $expense->category_id,
                'case_id' => $expense->case_id,
                'amount' => -1 * (float) $expense->amount,
                'method' => $expense->method,
                'account_id' => $expense->account_id,
                'beneficiary' => $expense->beneficiary,
                'expense_date' => now()->toDateString(),
                'journal_entry_id' => $reversalEntry->id,
                'reversal_of_id' => $expense->id,
                'paid_by' => $request->user()?->id,
            ]);
            $reversal->forceFill(['voucher_no' => $this->formatNo($reversal->id)])->save();

            $expense->account->increment('current_balance', (float) $expense->amount);

            $this->recordAudit($request, 'expense_reversed', Expense::class, $expense->id, [
                'reversed_by_voucher' => $reversal->voucher_no,
            ]);

            return $reversal;
        });
    }

    /** قيد الصرف: مدين حساب المصروف = دائن الصندوق/البنك الدافع (بحسب نوعه). */
    private function postExpenseJournal(Expense $expense, FinancialAccount $account, float $amount, Request $request)
    {
        $creditRole = $account->type === 'bank' ? AccountRole::BANK : AccountRole::CASH;

        return $this->journal->post(
            [
                'description' => 'سند صرف '.$expense->voucher_no,
                'reference_type' => 'expense',
                'reference_id' => $expense->id,
            ],
            [
                ['account_id' => $this->accounts->id(AccountRole::GENERAL_EXPENSE), 'debit' => $amount],
                ['account_id' => $this->accounts->id($creditRole), 'credit' => $amount],
            ],
            $request,
        );
    }

    private function formatNo(int $id): string
    {
        return 'EXP-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}

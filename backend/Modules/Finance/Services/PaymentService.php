<?php

namespace Modules\Finance\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Finance\Models\FinancialAccount;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\Payment;
use Modules\Finance\Support\AccountResolver;
use Modules\Finance\Support\AccountRole;

/**
 * منطق أعمال سندات القبض (Finance / Phase 6 · PR-6).
 *
 * يضمن:
 *  1) لا ازدواج: مفتاح Idempotency مخزّن ومفروض — إعادة الطلب بنفس المفتاح تُعيد السند
 *     نفسه بلا قيد جديد (فحص مسبق + قيد تفرّد يمسك التسابق).
 *  2) القيد الصحيح: مدين صندوق/بنك (بحسب نوع الحساب) = دائن ذمم العملاء، بالدور عبر Resolver.
 *  3) تحديث حالة الفاتورة: sent → partial → paid حسب المُحصَّل (المجموع يشمل السندات العكسية).
 *  4) لا حذف: التصحيح بعكس ينشئ سنداً بمبلغ سالب ويعكس القيد؛ الأصل وقيده يبقيان تاريخيّاً.
 */
class PaymentService
{
    use RecordsAudit;

    /** حالات الفاتورة التي تقبل تحصيلاً. */
    private const PAYABLE_STATUSES = [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL];

    public function __construct(
        private readonly JournalService $journal,
        private readonly AccountResolver $accounts,
    ) {}

    public function record(Invoice $invoice, array $data, ?string $idempotencyKey, Request $request): Payment
    {
        // 1) Idempotency: إعادة الطلب بنفس المفتاح تُعيد السند الموجود بلا قيد جديد.
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        if (! in_array($invoice->status, self::PAYABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'invoice' => 'لا يمكن تحصيل دفعة على فاتورة غير معتمدة أو مسدّدة/ملغاة.',
            ]);
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount > (float) $invoice->balance) {
            throw ValidationException::withMessages([
                'amount' => 'المبلغ يتجاوز المتبقّي على الفاتورة.',
            ]);
        }

        $account = FinancialAccount::findOrFail($data['account_id']);

        try {
            return DB::transaction(function () use ($invoice, $data, $amount, $account, $idempotencyKey, $request): Payment {
                $payment = Payment::create([
                    'receipt_no' => null,
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'amount' => $amount,
                    'method' => $data['method'],
                    'account_id' => $account->id,
                    'reference' => $data['reference'] ?? null,
                    'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                    'notes' => $data['notes'] ?? null,
                    'idempotency_key' => $idempotencyKey ?: null,
                    'received_by' => $request->user()?->id,
                ]);

                $entry = $this->postReceiptJournal($payment, $account, $amount, $request);

                $payment->forceFill([
                    'receipt_no' => $this->formatNo($payment->id),
                    'journal_entry_id' => $entry->id,
                ])->save();

                $account->increment('current_balance', $amount);
                $this->recomputeInvoice($invoice);

                $this->recordAudit($request, 'payment_recorded', Payment::class, $payment->id, [
                    'receipt_no' => $payment->receipt_no,
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                ]);

                return $payment;
            });
        } catch (UniqueConstraintViolationException $e) {
            // تسابق على نفس المفتاح: السند أُنشئ في طلب متزامن — أعِده بدل قيد مكرّر.
            $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }
            throw $e;
        }
    }

    /** عكس سند قبض: ينشئ سنداً سالباً ويعكس قيده، ويترك الأصل كما هو. */
    public function reverse(Payment $payment, Request $request): Payment
    {
        if ((float) $payment->amount <= 0) {
            throw ValidationException::withMessages(['payment' => 'لا يمكن عكس سند عكسي.']);
        }
        if ($payment->isReversed()) {
            throw ValidationException::withMessages(['payment' => 'السند مُعكوس مسبقاً.']);
        }
        if ($payment->journal_entry_id === null) {
            throw ValidationException::withMessages(['payment' => 'السند بلا قيد لعكسه.']);
        }

        return DB::transaction(function () use ($payment, $request): Payment {
            $reversalEntry = $this->journal->reverse($payment->journalEntry, $request, 'عكس سند '.$payment->receipt_no);

            $reversal = Payment::create([
                'receipt_no' => null,
                'invoice_id' => $payment->invoice_id,
                'client_id' => $payment->client_id,
                'amount' => -1 * (float) $payment->amount,
                'method' => $payment->method,
                'account_id' => $payment->account_id,
                'reference' => $payment->reference,
                'payment_date' => now()->toDateString(),
                'journal_entry_id' => $reversalEntry->id,
                'reversal_of_id' => $payment->id,
                'received_by' => $request->user()?->id,
            ]);
            $reversal->forceFill(['receipt_no' => $this->formatNo($reversal->id)])->save();

            $payment->account->decrement('current_balance', (float) $payment->amount);
            $this->recomputeInvoice($payment->invoice);

            $this->recordAudit($request, 'payment_reversed', Payment::class, $payment->id, [
                'reversed_by_receipt' => $reversal->receipt_no,
            ]);

            return $reversal;
        });
    }

    /** قيد القبض: مدين الصندوق/البنك (بحسب نوع الحساب) = دائن ذمم العملاء. */
    private function postReceiptJournal(Payment $payment, FinancialAccount $account, float $amount, Request $request)
    {
        $debitRole = $account->type === 'bank' ? AccountRole::BANK : AccountRole::CASH;

        return $this->journal->post(
            [
                'description' => 'تحصيل فاتورة '.$payment->invoice->invoice_no,
                'reference_type' => 'payment',
                'reference_id' => $payment->id,
            ],
            [
                ['account_id' => $this->accounts->id($debitRole), 'debit' => $amount],
                ['account_id' => $this->accounts->id(AccountRole::ACCOUNTS_RECEIVABLE), 'credit' => $amount],
            ],
            $request,
        );
    }

    /** يعيد حساب المُحصَّل والمتبقّي والحالة من مجموع السندات (يشمل العكسية السالبة). */
    private function recomputeInvoice(Invoice $invoice): void
    {
        $paid = round((float) $invoice->payments()->sum('amount'), 2);
        $total = (float) $invoice->total;
        $balance = round($total - $paid, 2);

        $status = $paid >= $total
            ? Invoice::STATUS_PAID
            : ($paid > 0 ? Invoice::STATUS_PARTIAL : Invoice::STATUS_SENT);

        $invoice->update(['paid_amount' => $paid, 'balance' => $balance, 'status' => $status]);
    }

    private function formatNo(int $id): string
    {
        return 'RCP-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}

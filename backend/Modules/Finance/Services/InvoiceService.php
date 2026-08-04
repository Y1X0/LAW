<?php

namespace Modules\Finance\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Support\AccountResolver;
use Modules\Finance\Support\AccountRole;

/**
 * منطق أعمال الفواتير (Finance / Phase 6 · PR-4).
 *
 * مصدر الحقيقة الوحيد للمبالغ والقيد: يحسب الإجماليات من البنود (ضريبة لكل بند + خصم
 * على مستوى الفاتورة) ولا يقبل أي مبلغ محسوب من العميل، ويُرحِّل قيد الإيراد عند الاعتماد
 * عبر JournalService + AccountResolver (بالدور لا بالرقم).
 *
 * الحالة: draft فقط قابلة للتعديل/الاعتماد/الإلغاء. بعد الاعتماد (sent) تصير الفاتورة
 * وقيدها نهائيين — أي تصحيح لاحق يكون بعكس القيد (طبقة لاحقة).
 */
class InvoiceService
{
    use RecordsAudit;

    public function __construct(
        private readonly JournalService $journal,
        private readonly AccountResolver $accounts,
    ) {}

    public function create(array $data, Request $request): Invoice
    {
        return DB::transaction(function () use ($data, $request): Invoice {
            $totals = $this->computeTotals($data['items'], (float) ($data['discount'] ?? 0));

            $invoice = Invoice::create([
                'invoice_no' => null,
                'client_id' => $data['client_id'],
                'case_id' => $data['case_id'] ?? null,
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax'],
                'discount' => $totals['discount'],
                'total' => $totals['total'],
                'paid_amount' => 0,
                'balance' => $totals['total'],
                'status' => Invoice::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $invoice->forceFill(['invoice_no' => $this->formatNo($invoice->id)])->save();
            $this->replaceItems($invoice, $totals['items']);

            $this->recordAudit($request, 'invoice_created', Invoice::class, $invoice->id, [
                'invoice_no' => $invoice->invoice_no,
                'total' => $invoice->total,
            ]);

            return $invoice->load('items');
        });
    }

    public function update(Invoice $invoice, array $data, Request $request): Invoice
    {
        $this->assertDraft($invoice);

        return DB::transaction(function () use ($invoice, $data, $request): Invoice {
            $totals = $this->computeTotals($data['items'], (float) ($data['discount'] ?? 0));

            $invoice->update([
                'client_id' => $data['client_id'],
                'case_id' => $data['case_id'] ?? null,
                'issue_date' => $data['issue_date'] ?? $invoice->issue_date,
                'due_date' => $data['due_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax'],
                'discount' => $totals['discount'],
                'total' => $totals['total'],
                'balance' => $totals['total'],
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice->items()->delete();
            $this->replaceItems($invoice, $totals['items']);

            $this->recordAudit($request, 'invoice_updated', Invoice::class, $invoice->id);

            return $invoice->load('items');
        });
    }

    /** اعتماد الفاتورة: draft → sent مع ترحيل قيد الإيراد آلياً. */
    public function approve(Invoice $invoice, Request $request): Invoice
    {
        $this->assertDraft($invoice);

        if ((float) $invoice->total <= 0) {
            throw ValidationException::withMessages(['total' => 'لا يمكن اعتماد فاتورة بإجمالي صفر أو أقل.']);
        }

        return DB::transaction(function () use ($invoice, $request): Invoice {
            $entry = $this->journal->post(
                [
                    'description' => 'إيراد فاتورة '.$invoice->invoice_no,
                    'reference_type' => 'invoice',
                    'reference_id' => $invoice->id,
                ],
                $this->buildJournalLines($invoice),
                $request,
            );

            $invoice->update([
                'status' => Invoice::STATUS_SENT,
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
                'journal_entry_id' => $entry->id,
            ]);

            $this->recordAudit($request, 'invoice_approved', Invoice::class, $invoice->id, [
                'journal_entry' => $entry->entry_no,
            ]);

            return $invoice->load('items', 'journalEntry');
        });
    }

    /** إلغاء مسودّة (قبل الاعتماد). إلغاء فاتورة معتمدة يتطلّب عكس القيد — طبقة لاحقة. */
    public function cancel(Invoice $invoice, Request $request): Invoice
    {
        $this->assertDraft($invoice);

        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);
        $this->recordAudit($request, 'invoice_cancelled', Invoice::class, $invoice->id);

        return $invoice;
    }

    /**
     * يحسب الإجماليات بالهللات (تجنّباً لأخطاء العائم). خصم على مستوى الفاتورة محصور 0..subtotal.
     *
     * @return array{subtotal:float, tax:float, discount:float, total:float, items:array<int, array<string, mixed>>}
     */
    private function computeTotals(array $items, float $discount): array
    {
        $subtotalCents = 0;
        $taxCents = 0;
        $computed = [];

        foreach ($items as $item) {
            $quantity = round((float) $item['quantity'], 2);
            $unitPrice = round((float) $item['unit_price'], 2);
            $rate = round((float) ($item['tax_rate'] ?? 0), 2);

            $lineTotal = round($quantity * $unitPrice, 2);
            $lineTax = round($lineTotal * $rate / 100, 2);

            $subtotalCents += (int) round($lineTotal * 100);
            $taxCents += (int) round($lineTax * 100);

            $computed[] = [
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $rate,
                'line_total' => $lineTotal,
            ];
        }

        $subtotal = $subtotalCents / 100;
        $tax = $taxCents / 100;
        $discount = max(0.0, min(round($discount, 2), $subtotal));
        $total = round($subtotal - $discount + $tax, 2);

        return ['subtotal' => $subtotal, 'tax' => $tax, 'discount' => $discount, 'total' => $total, 'items' => $computed];
    }

    /**
     * سطور قيد الإيراد: مدين ذمم العملاء (الإجمالي) = دائن إيراد الأتعاب (الصافي بعد الخصم)
     * + دائن الضريبة المستحقة (إن وُجدت). تُبنى بالدور عبر AccountResolver.
     */
    private function buildJournalLines(Invoice $invoice): array
    {
        $lines = [[
            'account_id' => $this->accounts->id(AccountRole::ACCOUNTS_RECEIVABLE),
            'debit' => (float) $invoice->total,
            'notes' => 'ذمم '.$invoice->invoice_no,
        ]];

        $revenue = round((float) $invoice->subtotal - (float) $invoice->discount, 2);
        if ($revenue > 0) {
            $lines[] = ['account_id' => $this->accounts->id(AccountRole::FEE_REVENUE), 'credit' => $revenue];
        }

        $tax = (float) $invoice->tax_amount;
        if ($tax > 0) {
            $lines[] = ['account_id' => $this->accounts->id(AccountRole::VAT_PAYABLE), 'credit' => $tax];
        }

        return $lines;
    }

    private function replaceItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $invoice->items()->create($item);
        }
    }

    private function assertDraft(Invoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن تعديل أو اعتماد أو إلغاء فاتورة غير مسودّة.',
            ]);
        }
    }

    private function formatNo(int $id): string
    {
        return 'INV-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}

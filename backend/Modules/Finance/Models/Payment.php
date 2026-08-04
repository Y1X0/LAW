<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Finance\Factories\PaymentFactory;

/**
 * سند قبض (Finance / Phase 6 · PR-6).
 *
 * المبلغ الموجب تحصيل، والسالب سند عكسي (reversal). لا يُعدَّل/يُحذف بعد الترحيل —
 * التصحيح بعكس فقط (يتبع سياسة الدفتر: الحركات المالية تُعكَس لا تُحذف).
 */
class Payment extends Model
{
    use HasFactory;

    public const METHODS = ['cash', 'bank_transfer', 'cheque', 'card'];

    protected $fillable = [
        'receipt_no', 'invoice_id', 'client_id', 'amount', 'method', 'account_id', 'reference',
        'payment_date', 'notes', 'journal_entry_id', 'reversal_of_id', 'idempotency_key',
        'received_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** هل عُكس هذا السند بسند آخر؟ (لا نخزّن الحالة على الأصل حفاظاً على تاريخه). */
    public function isReversed(): bool
    {
        return self::where('reversal_of_id', $this->id)->exists();
    }

    protected static function newFactory(): Factory
    {
        return PaymentFactory::new();
    }
}

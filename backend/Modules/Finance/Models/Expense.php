<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Finance\Factories\ExpenseFactory;

/**
 * سند صرف (Finance / Phase 6 · PR-8).
 *
 * المبلغ الموجب صرف، والسالب سند عكسي (reversal). لا يُحذف — التصحيح بعكس فقط.
 */
class Expense extends Model
{
    use HasFactory;

    public const METHODS = ['cash', 'bank_transfer', 'cheque'];

    protected $fillable = [
        'voucher_no', 'category_id', 'case_id', 'amount', 'method', 'account_id', 'beneficiary',
        'expense_date', 'description', 'journal_entry_id', 'reversal_of_id', 'paid_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isReversed(): bool
    {
        return self::where('reversal_of_id', $this->id)->exists();
    }

    protected static function newFactory(): Factory
    {
        return ExpenseFactory::new();
    }
}

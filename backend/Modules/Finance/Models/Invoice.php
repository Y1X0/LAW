<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Finance\Factories\InvoiceFactory;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\LegalCase;

/**
 * فاتورة (Finance / Phase 6 · PR-4).
 *
 * دورة الحياة: draft (قابلة للتعديل) → sent (معتمدة، قيد مُرحَّل، نهائية) أو cancelled.
 * partial/paid/overdue قيم محجوزة لطبقات لاحقة (مدفوعات/جدولة). الإجماليات والقيد
 * تُدار حصراً عبر InvoiceService — لا تُحسب في الواجهة.
 */
class Invoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_SENT, self::STATUS_PARTIAL,
        self::STATUS_PAID, self::STATUS_OVERDUE, self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'invoice_no', 'client_id', 'case_id', 'issue_date', 'due_date',
        'subtotal', 'tax_amount', 'discount', 'total', 'paid_amount', 'balance',
        'status', 'notes', 'journal_entry_id', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'approved_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    protected static function newFactory(): Factory
    {
        return InvoiceFactory::new();
    }
}

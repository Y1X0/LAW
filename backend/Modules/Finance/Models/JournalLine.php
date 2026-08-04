<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Finance\Exceptions\ImmutableJournalEntryException;
use Modules\Finance\Factories\JournalLineFactory;

/**
 * سطر قيد يومية (Finance / Phase 6 · PR-3).
 *
 * إمّا مدين أو دائن. غير قابل للتعديل/الحذف متى كان قيده مُرحَّلاً (مناعة القيد تشمل سطوره).
 */
class JournalLine extends Model
{
    use HasFactory;

    protected $fillable = ['journal_entry_id', 'account_id', 'debit', 'credit', 'notes'];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $line): void {
            if (self::entryIsPosted($line->journal_entry_id)) {
                throw new ImmutableJournalEntryException('لا يمكن تعديل سطر قيد مُرحَّل.');
            }
        });

        static::deleting(function (self $line): void {
            if (self::entryIsPosted($line->journal_entry_id)) {
                throw new ImmutableJournalEntryException('لا يمكن حذف سطر قيد مُرحَّل.');
            }
        });
    }

    private static function entryIsPosted(?int $entryId): bool
    {
        return $entryId !== null && JournalEntry::whereKey($entryId)->value('posted') === true;
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    protected static function newFactory(): Factory
    {
        return JournalLineFactory::new();
    }
}

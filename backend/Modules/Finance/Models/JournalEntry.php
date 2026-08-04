<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Finance\Exceptions\ImmutableJournalEntryException;
use Modules\Finance\Factories\JournalEntryFactory;

/**
 * قيد يومية (رأس) (Finance / Phase 6 · PR-3).
 *
 * يُرحَّل عبر JournalService فقط (ذرّياً مع سطوره). بمجرد أن يصبح posted=true يصير
 * نهائيّاً: يمنع النموذج أي تعديل/حذف له بعد الترحيل — التصحيح بقيد عكس.
 */
class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_no', 'entry_date', 'description', 'reference_type', 'reference_id',
        'posted', 'reversal_of_id', 'created_by', 'posted_by', 'posted_at', 'approved_by', 'approved_at',
    ];

    protected $attributes = [
        'posted' => false,
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted' => 'boolean',
            'posted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // منع تعديل قيد سبق ترحيله (rule: لا تعديل بعد الترحيل — عكس فقط).
        static::updating(function (self $entry): void {
            if ($entry->getOriginal('posted') === true) {
                throw new ImmutableJournalEntryException('لا يمكن تعديل قيد مُرحَّل — استخدم قيد عكس.');
            }
        });

        static::deleting(function (self $entry): void {
            if ($entry->getOriginal('posted') === true || $entry->posted === true) {
                throw new ImmutableJournalEntryException('لا يمكن حذف قيد مُرحَّل.');
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    /** هل عُكس هذا القيد بقيد آخر؟ (لا نخزّن الحالة على الأصل حفاظاً على مناعته). */
    public function isReversed(): bool
    {
        return $this->reversedBy()->exists();
    }

    protected static function newFactory(): Factory
    {
        return JournalEntryFactory::new();
    }
}

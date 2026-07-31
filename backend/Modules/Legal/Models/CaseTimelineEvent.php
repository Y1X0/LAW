<?php

namespace Modules\Legal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Legal\Factories\CaseTimelineEventFactory;
use RuntimeException;

/**
 * حدث في الخط الزمني للقضية (Legal / LC-4) — سجلّ Append-Only.
 *
 * لا يُعدَّل ولا يُحذف بعد الإنشاء (يُفرَض هنا على مستوى النموذج + غياب مسارات التعديل/الحذف).
 * التصحيح يكون بإضافة حدث جديد.
 */
class CaseTimelineEvent extends Model
{
    use HasFactory;

    protected $fillable = ['case_id', 'title', 'event_type', 'event_date', 'description', 'created_by'];

    protected $casts = [
        'event_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('أحداث الخط الزمني للقضية Append-Only ولا يمكن تعديلها.');
        });
        static::deleting(function (): void {
            throw new RuntimeException('أحداث الخط الزمني للقضية Append-Only ولا يمكن حذفها.');
        });
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): Factory
    {
        return CaseTimelineEventFactory::new();
    }
}

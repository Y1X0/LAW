<?php

namespace Modules\Legal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Legal\Factories\HearingFactory;

/**
 * جلسة قضية (Legal / LC-3).
 *
 * الرؤية ترث عزل القضية (لا صلاحيات عرض مستقلة). الاستعلامات المدمجة:
 * القادمة (upcoming) / الأخيرة (past) / المؤجّلة (postponed).
 */
class Hearing extends Model
{
    use HasFactory;

    public const STATUSES = ['scheduled', 'held', 'postponed', 'cancelled'];

    protected $fillable = [
        'case_id', 'scheduled_at', 'type', 'status', 'location', 'outcome',
        'postponed_to', 'postponed_reason', 'notes', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'postponed_to' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'scheduled',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** الجلسات القادمة: مجدولة ولم يحن وقتها بعد. */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')->where('scheduled_at', '>=', now());
    }

    /** الجلسات السابقة (الأخيرة): التي انعقدت فعلاً (held). */
    public function scopePast(Builder $query): Builder
    {
        return $query->where('status', 'held');
    }

    /** الجلسات المؤجّلة. */
    public function scopePostponed(Builder $query): Builder
    {
        return $query->where('status', 'postponed');
    }

    protected static function newFactory(): Factory
    {
        return HearingFactory::new();
    }
}

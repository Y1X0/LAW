<?php

namespace Modules\Legal\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Models\Employee;
use Modules\Legal\Factories\DailyWorklogFactory;

/**
 * إنجاز يومي ذاتي (Legal / LC-5) — سجل واحد لكل (موظف، يوم).
 */
class DailyWorklog extends Model
{
    use HasFactory;

    protected $fillable = ['employee_id', 'work_date', 'done_today', 'plan_tomorrow'];

    // work_date يُخزَّن ويُعرَض كسلسلة Y-m-d (بلا وقت) لاتساق المطابقة والعرض.

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    protected static function newFactory(): Factory
    {
        return DailyWorklogFactory::new();
    }
}

<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Branch;

class WorkShift extends Model
{
    protected $fillable = ['branch_id', 'name', 'start_time', 'end_time', 'grace_minutes', 'weekdays', 'is_active'];

    protected $casts = [
        'weekdays' => 'array',
        'is_active' => 'boolean',
        'grace_minutes' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}

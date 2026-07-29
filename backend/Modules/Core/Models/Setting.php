<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    protected $fillable = ['branch_id', 'group', 'key', 'value'];

    protected $casts = ['value' => 'array'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}

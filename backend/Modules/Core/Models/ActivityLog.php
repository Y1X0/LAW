<?php

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'description', 'subject_type', 'subject_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

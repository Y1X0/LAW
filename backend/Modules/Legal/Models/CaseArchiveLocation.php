<?php

namespace Modules\Legal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Legal\Factories\CaseArchiveLocationFactory;

/**
 * موقع أرشيف ورقي لقضية (Legal / LG-3) — فهرسة فقط. عدّة مواقع لكل قضية.
 */
class CaseArchiveLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id', 'file_title', 'archive_room', 'cabinet', 'shelf', 'drawer', 'file_number', 'notes', 'created_by',
    ];

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
        return CaseArchiveLocationFactory::new();
    }
}

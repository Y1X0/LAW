<?php

namespace Modules\Legal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Legal\Factories\CasePartyFactory;

/**
 * طرف في القضية (Legal / LG-2): مدّعي/مدّعى عليه/شاهد/آخر — مرتبط بالقضية فقط.
 */
class CaseParty extends Model
{
    use HasFactory;

    public const TYPES = ['plaintiff', 'defendant', 'witness', 'other'];

    protected $fillable = ['case_id', 'name', 'type', 'phone', 'notes', 'created_by'];

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
        return CasePartyFactory::new();
    }
}

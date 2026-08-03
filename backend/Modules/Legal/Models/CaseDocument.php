<?php

namespace Modules\Legal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Legal\Factories\CaseDocumentFactory;

/**
 * مستند قضية (Legal / LC-4) — بيانات وصفية فقط (metadata + مسار)، بلا رفع فعلي.
 */
class CaseDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id', 'title', 'document_type', 'description',
        'storage_disk', 'storage_path', 'original_name', 'mime_type', 'size_bytes', 'checksum',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected static function newFactory(): Factory
    {
        return CaseDocumentFactory::new();
    }
}

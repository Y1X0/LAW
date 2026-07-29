<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDocument extends Model
{
    use SoftDeletes;

    public const DOC_TYPES = ['contract', 'id_copy', 'certificate', 'other'];

    protected $fillable = [
        'employee_id', 'doc_type', 'title', 'file_path', 'expiry_date', 'uploaded_by',
    ];

    protected $casts = ['expiry_date' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

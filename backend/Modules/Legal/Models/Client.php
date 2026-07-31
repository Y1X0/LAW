<?php

namespace Modules\Legal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Legal\Factories\ClientFactory;

/**
 * عميل المكتب (Legal / LC-1).
 *
 * لا حذف فعلي — التعطيل عبر status (active/inactive) للحفاظ على ارتباط القضايا مستقبلاً.
 */
class Client extends Model
{
    use HasFactory;

    public const TYPES = ['individual', 'company'];

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'name', 'type', 'phone', 'email', 'national_id', 'address', 'status', 'notes', 'created_by',
    ];

    /** قيم افتراضية على مستوى النموذج (تنعكس في الذاكرة، لا فقط في قاعدة البيانات). */
    protected $attributes = [
        'status' => 'active',
    ];

    /** المستخدم الذي أنشأ السجل. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): Factory
    {
        return ClientFactory::new();
    }
}

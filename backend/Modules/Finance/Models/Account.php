<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Finance\Factories\AccountFactory;

/**
 * حساب في دليل الحسابات (Finance / Phase 6 · PR-2).
 *
 * ليس حساب صندوق/بنك (ذاك FinancialAccount) — هذا حساب دفتر الأستاذ الذي تشير إليه
 * سطور القيود (journal_lines.account_id). الحسابات النظامية تُحلّ عبر system_role.
 */
class Account extends Model
{
    use HasFactory;

    protected $table = 'chart_of_accounts';

    public const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    protected $fillable = ['code', 'name', 'type', 'parent_id', 'system_role', 'is_active'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    protected static function newFactory(): Factory
    {
        return AccountFactory::new();
    }
}

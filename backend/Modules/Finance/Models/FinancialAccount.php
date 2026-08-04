<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Finance\Factories\FinancialAccountFactory;

/**
 * حساب نقدي (صندوق/بنك) (Finance / Phase 6 · PR-1).
 */
class FinancialAccount extends Model
{
    use HasFactory;

    public const TYPES = ['cash', 'bank'];

    protected $fillable = [
        'name', 'type', 'account_number', 'opening_balance', 'current_balance', 'currency', 'is_active', 'created_by',
    ];

    protected $attributes = [
        'type' => 'cash',
        'currency' => 'SAR',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return FinancialAccountFactory::new();
    }
}

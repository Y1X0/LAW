<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Finance\Factories\TaxFactory;

/**
 * نسبة ضريبية (Finance / Phase 6 · PR-1).
 */
class Tax extends Model
{
    use HasFactory;

    /** اسم ضريبة القيمة المضافة القياسية. */
    public const VAT = 'VAT';

    protected $fillable = ['name', 'rate', 'is_active'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return TaxFactory::new();
    }
}

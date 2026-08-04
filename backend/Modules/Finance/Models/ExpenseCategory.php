<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Finance\Factories\ExpenseCategoryFactory;

/**
 * تصنيف مصروف (Finance / Phase 6 · PR-1).
 */
class ExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_active'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ExpenseCategoryFactory::new();
    }
}

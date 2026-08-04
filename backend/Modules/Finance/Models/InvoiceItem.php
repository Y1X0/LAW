<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Finance\Factories\InvoiceItemFactory;

/**
 * بند فاتورة (Finance / Phase 6 · PR-4).
 */
class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = ['invoice_id', 'description', 'quantity', 'unit_price', 'tax_rate', 'line_total'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected static function newFactory(): Factory
    {
        return InvoiceItemFactory::new();
    }
}

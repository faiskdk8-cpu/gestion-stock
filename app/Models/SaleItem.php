<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'cmp_at_sale',
        'benefit',
        'stock_before',
    ];

    protected function casts(): array
    {
        return [
            'quantity'     => 'decimal:4',
            'unit_price'   => 'decimal:4',
            'cmp_at_sale'  => 'decimal:4',
            'benefit'      => 'decimal:4',
            'stock_before' => 'decimal:4',
        ];
    }

    // ─── Relations ──────────────────────────────────────────────────────────

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovement()
    {
        return $this->morphOne(StockMovement::class, 'moveable');
    }

    // ─── Accesseurs ─────────────────────────────────────────────────────────

    /** Sous-total CA de cette ligne */
    public function getSubTotalAttribute(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 4);
    }

    /** Marge brute en % = (bénéfice / sous-total) × 100 */
    public function getMarginPercentAttribute(): float
    {
        $subTotal = $this->getSubTotalAttribute();
        if ($subTotal == 0) return 0;
        return round(((float) $this->benefit / $subTotal) * 100, 2);
    }
}

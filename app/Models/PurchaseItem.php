<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'unit_price',
        'cmp_before',
        'cmp_after',
        'stock_before',
    ];

    protected function casts(): array
    {
        return [
            'quantity'     => 'decimal:4',
            'unit_price'   => 'decimal:4',
            'cmp_before'   => 'decimal:4',
            'cmp_after'    => 'decimal:4',
            'stock_before' => 'decimal:4',
        ];
    }

    // ─── Relations ──────────────────────────────────────────────────────────

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
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

    /** Sous-total de cette ligne : quantité × prix unitaire */
    public function getSubTotalAttribute(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 4);
    }

    /** Variation du CMP causée par cet achat */
    public function getCmpDeltaAttribute(): float
    {
        return round((float) $this->cmp_after - (float) $this->cmp_before, 4);
    }
}

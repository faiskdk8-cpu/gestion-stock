<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type',
        'direction',
        'quantity',
        'stock_after',
        'cmp_after',
        'moveable_type',
        'moveable_id',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'quantity'    => 'decimal:4',
            'stock_after' => 'decimal:4',
            'cmp_after'   => 'decimal:4',
            'date'        => 'date',
        ];
    }

    // ─── Relations ──────────────────────────────────────────────────────────

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** Relation polymorphique vers PurchaseItem ou SaleItem */
    public function moveable()
    {
        return $this->morphTo();
    }

    // ─── Accesseurs ─────────────────────────────────────────────────────────

    /** Libellé lisible du mouvement */
    public function getDescriptionAttribute(): string
    {
        return match($this->type) {
            'purchase' => "Achat : +{$this->quantity} {$this->product->unit}",
            'sale'     => "Vente : -{$this->quantity} {$this->product->unit}",
            default    => "Mouvement de stock",
        };
    }
}

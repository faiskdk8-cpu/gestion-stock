<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    // ─── Relations ──────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    // ─── Accesseurs ─────────────────────────────────────────────────────────

    /** Chiffre d'affaires total de cette vente = Σ (quantité × prix de vente) */
    public function getTotalRevenueAttribute(): float
    {
        return round(
            $this->items->sum(fn($item) => (float)$item->quantity * (float)$item->unit_price),
            4
        );
    }

    /** Bénéfice brut total de cette vente = Σ benefit de chaque ligne */
    public function getTotalBenefitAttribute(): float
    {
        return round(
            $this->items->sum(fn($item) => (float)$item->benefit),
            4
        );
    }

    /** Coût total au CMP = Σ (quantité × CMP au moment de la vente) */
    public function getTotalCostAttribute(): float
    {
        return round(
            $this->items->sum(fn($item) => (float)$item->quantity * (float)$item->cmp_at_sale),
            4
        );
    }
}

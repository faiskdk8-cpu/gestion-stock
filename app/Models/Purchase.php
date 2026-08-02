<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
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
        return $this->hasMany(PurchaseItem::class);
    }

    // ─── Accesseurs ─────────────────────────────────────────────────────────

    /** Coût total de l'achat = Σ (quantité × prix unitaire) */
    public function getTotalCostAttribute(): float
    {
        return round(
            $this->items->sum(fn($item) => (float)$item->quantity * (float)$item->unit_price),
            4
        );
    }

    /** Nombre de lignes dans cet achat */
    public function getItemsCountAttribute(): int
    {
        return $this->items->count();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'unit',
        'alert_threshold',
        'current_stock',
        'current_cmp',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'alert_threshold' => 'decimal:4',
            'current_stock'   => 'decimal:4',
            'current_cmp'     => 'decimal:4',
            'is_active'       => 'boolean',
        ];
    }

    // ─── Relations ──────────────────────────────────────────────────────────

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class)->orderBy('date')->orderBy('id');
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────

    /** Uniquement les produits actifs */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Uniquement les produits dont le stock est ≤ au seuil d'alerte (et seuil > 0) */
    public function scopeLowStock($query)
    {
        return $query->where('is_active', true)
                     ->where('alert_threshold', '>', 0)
                     ->whereColumn('current_stock', '<=', 'alert_threshold');
    }

    /** Produits actifs avec du stock disponible (pour formulaire de vente) */
    public function scopeWithStock($query)
    {
        return $query->where('is_active', true)
                     ->where('current_stock', '>', 0);
    }

    // ─── Accesseurs ─────────────────────────────────────────────────────────

    /** Valeur du stock = quantité disponible × CMP courant */
    public function getStockValueAttribute(): float
    {
        return round((float) $this->current_stock * (float) $this->current_cmp, 4);
    }

    /** true si le produit est en alerte de stock faible */
    public function getIsLowStockAttribute(): bool
    {
        return $this->alert_threshold > 0
            && $this->current_stock <= $this->alert_threshold;
    }

    // ─── Méthodes métier ────────────────────────────────────────────────────

    /**
     * Recalcule le CMP et met à jour le stock après un achat.
     *
     * Formule CMP :
     *   nouveau_CMP = (stock_avant × CMP_avant + quantité × prix_achat) / (stock_avant + quantité)
     *
     * @param  float $quantityBought  Quantité achetée (> 0)
     * @param  float $unitPrice       Prix d'achat unitaire
     * @return array{cmp_before: float, cmp_after: float, stock_before: float}
     */
    public function applyPurchase(float $quantityBought, float $unitPrice): array
    {
        $stockBefore = (float) $this->current_stock;
        $cmpBefore   = (float) $this->current_cmp;

        // Si le stock était à 0, le nouveau CMP = prix d'achat directement
        $totalQuantity = $stockBefore + $quantityBought;
        $newCmp = $totalQuantity > 0
            ? round(($stockBefore * $cmpBefore + $quantityBought * $unitPrice) / $totalQuantity, 4)
            : round($unitPrice, 4);

        $this->current_stock = round($stockBefore + $quantityBought, 4);
        $this->current_cmp   = $newCmp;
        $this->save();

        return [
            'cmp_before'   => $cmpBefore,
            'cmp_after'    => $newCmp,
            'stock_before' => $stockBefore,
        ];
    }

    /**
     * Décrémente le stock après une vente.
     * Le CMP n'est PAS modifié par une vente.
     *
     * @param  float $quantitySold  Quantité vendue
     * @return array{cmp_at_sale: float, stock_before: float, benefit: float}
     */
    public function applySale(float $quantitySold, float $salePrice): array
    {
        $stockBefore = (float) $this->current_stock;
        $cmpAtSale   = (float) $this->current_cmp;

        // Calcul du bénéfice brut : (prix vente - CMP) × quantité
        $benefit = round(($salePrice - $cmpAtSale) * $quantitySold, 4);

        $this->current_stock = round($stockBefore - $quantitySold, 4);
        $this->save();

        return [
            'cmp_at_sale'  => $cmpAtSale,
            'stock_before' => $stockBefore,
            'benefit'      => $benefit,
        ];
    }

    /**
     * Vérifie si une vente est possible (stock suffisant).
     */
    public function canSell(float $quantity): bool
    {
        return (float) $this->current_stock >= $quantity;
    }
}

<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Enregistre un achat complet de façon atomique.
     *
     * Pour chaque ligne :
     *   1. Récupère le produit avec lock pessimiste (évite les race conditions)
     *   2. Calcule le nouveau CMP
     *   3. Met à jour le stock et le CMP du produit
     *   4. Crée la ligne PurchaseItem avec les valeurs avant/après
     *   5. Enregistre le mouvement de stock
     *
     * @param  array $data  {user_id, date, note?, items: [{product_id, quantity, unit_price}]}
     * @return Purchase
     */
    public function createPurchase(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {

            // 1. Créer l'entête de l'achat
            $purchase = Purchase::create([
                'user_id' => $data['user_id'],
                'date'    => $data['date'],
                'note'    => $data['note'] ?? null,
            ]);

            // 2. Traiter chaque ligne d'achat
            foreach ($data['items'] as $itemData) {
                // Lock pessimiste : empêche une lecture/écriture concurrente du même produit
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);

                $quantity  = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];

                // 3. Appliquer l'achat sur le produit (recalcul CMP + mise à jour stock)
                $cmpData = $product->applyPurchase($quantity, $unitPrice);

                // 4. Créer la ligne d'achat avec snapshot CMP avant/après
                $item = PurchaseItem::create([
                    'purchase_id'  => $purchase->id,
                    'product_id'   => $product->id,
                    'quantity'     => $quantity,
                    'unit_price'   => $unitPrice,
                    'cmp_before'   => $cmpData['cmp_before'],
                    'cmp_after'    => $cmpData['cmp_after'],
                    'stock_before' => $cmpData['stock_before'],
                ]);

                // 5. Enregistrer le mouvement dans le journal
                StockMovement::create([
                    'product_id'    => $product->id,
                    'type'          => 'purchase',
                    'direction'     => '+',
                    'quantity'      => $quantity,
                    'stock_after'   => (float) $product->current_stock,
                    'cmp_after'     => $cmpData['cmp_after'],
                    'moveable_type' => PurchaseItem::class,
                    'moveable_id'   => $item->id,
                    'date'          => $data['date'],
                ]);
            }

            return $purchase->load('items.product');
        });
    }

    /**
     * Enregistre une vente complète de façon atomique.
     *
     * Pour chaque ligne :
     *   1. Vérifie que le stock est suffisant (erreur si insuffisant)
     *   2. Capture le CMP actuel du produit (figé dans sale_items)
     *   3. Calcule le bénéfice brut
     *   4. Décrémente le stock
     *   5. Enregistre la ligne SaleItem et le mouvement
     *
     * @param  array $data  {user_id, date, note?, items: [{product_id, quantity, unit_price}]}
     * @return Sale
     *
     * @throws \Exception si le stock est insuffisant pour un produit
     */
    public function createSale(array $data): Sale
    {
        return DB::transaction(function () use ($data) {

            // 1. Créer l'entête de la vente
            $sale = Sale::create([
                'user_id' => $data['user_id'],
                'date'    => $data['date'],
                'note'    => $data['note'] ?? null,
            ]);

            // 2. Traiter chaque ligne de vente
            foreach ($data['items'] as $itemData) {
                $product   = Product::lockForUpdate()->findOrFail($itemData['product_id']);
                $quantity  = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];

                // 3. Vérification stock suffisant (RG-V02)
                if (! $product->canSell($quantity)) {
                    throw new \Exception(
                        "Stock insuffisant pour \"{$product->name}\". " .
                        "Disponible : {$product->current_stock} {$product->unit}."
                    );
                }

                // 4. Appliquer la vente (décrémente stock, capture CMP, calcule bénéfice)
                $saleData = $product->applySale($quantity, $unitPrice);

                // 5. Créer la ligne de vente avec snapshot CMP
                $item = SaleItem::create([
                    'sale_id'      => $sale->id,
                    'product_id'   => $product->id,
                    'quantity'     => $quantity,
                    'unit_price'   => $unitPrice,
                    'cmp_at_sale'  => $saleData['cmp_at_sale'],
                    'benefit'      => $saleData['benefit'],
                    'stock_before' => $saleData['stock_before'],
                ]);

                // 6. Enregistrer le mouvement
                StockMovement::create([
                    'product_id'    => $product->id,
                    'type'          => 'sale',
                    'direction'     => '-',
                    'quantity'      => $quantity,
                    'stock_after'   => (float) $product->current_stock,
                    'cmp_after'     => $saleData['cmp_at_sale'],
                    'moveable_type' => SaleItem::class,
                    'moveable_id'   => $item->id,
                    'date'          => $data['date'],
                ]);
            }

            return $sale->load('items.product');
        });
    }

    /**
     * Annule une vente et restaure le stock de chaque produit.
     * Le CMP n'est PAS rétabli (règle RG-V05).
     *
     * @param  Sale $sale
     */
    public function cancelSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {

            $sale->load('items.product');

            foreach ($sale->items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item->product_id);

                // Restaurer la quantité dans le stock
                $product->current_stock = round((float) $product->current_stock + (float) $item->quantity, 4);
                $product->save();

                // Supprimer le mouvement associé à cette ligne
                StockMovement::where('moveable_type', SaleItem::class)
                              ->where('moveable_id', $item->id)
                              ->delete();
            }

            // Supprimer les lignes puis l'entête
            $sale->items()->delete();
            $sale->delete();
        });
    }

    /**
     * Annule un achat et restaure le stock/CMP si possible.
     * Interdit si des ventes postérieures existent sur le même produit (RG-A06).
     *
     * @param  Purchase $purchase
     * @throws \Exception si une vente postérieure bloque l'annulation
     */
    public function cancelPurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {

            $purchase->load('items.product');

            foreach ($purchase->items as $item) {
                // Vérification RG-A06 : aucune vente postérieure sur ce produit
                $hasSaleAfter = SaleItem::where('product_id', $item->product_id)
                    ->whereHas('sale', fn($q) => $q->where('date', '>=', $purchase->date))
                    ->exists();

                if ($hasSaleAfter) {
                    throw new \Exception(
                        "Impossible de supprimer cet achat : des ventes ont été enregistrées " .
                        "après cette date pour le produit \"{$item->product->name}\"."
                    );
                }

                $product = Product::lockForUpdate()->findOrFail($item->product_id);

                // Restaurer le stock et le CMP d'avant l'achat
                $product->current_stock = round((float) $product->current_stock - (float) $item->quantity, 4);
                $product->current_cmp   = (float) $item->cmp_before;
                $product->save();

                // Supprimer le mouvement
                StockMovement::where('moveable_type', PurchaseItem::class)
                              ->where('moveable_id', $item->id)
                              ->delete();
            }

            $purchase->items()->delete();
            $purchase->delete();
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Quantité vendue (> 0, ≤ stock disponible — vérifié au niveau applicatif)
            $table->decimal('quantity', 10, 4);
            // Prix de vente unitaire (peut varier d'une vente à l'autre)
            $table->decimal('unit_price', 10, 4);
            // CMP du produit AU MOMENT de la vente — crucial pour le calcul du bénéfice
            // Ce champ est figé lors de la vente et ne change jamais ensuite
            $table->decimal('cmp_at_sale', 10, 4);
            // Bénéfice brut = (unit_price - cmp_at_sale) × quantity
            // Peut être négatif si vente à perte
            $table->decimal('benefit', 10, 4);
            // Stock du produit AVANT cette vente — pour l'historique
            $table->decimal('stock_before', 10, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};

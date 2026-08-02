<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Quantité achetée (doit être > 0 — vérifié au niveau applicatif)
            $table->decimal('quantity', 10, 4);
            // Prix d'achat unitaire à ce moment précis (peut varier d'un achat à l'autre)
            $table->decimal('unit_price', 10, 4);
            // CMP du produit AVANT cet achat — conservé pour l'historique et les recalculs
            $table->decimal('cmp_before', 10, 4)->default(0);
            // CMP du produit APRÈS cet achat — résultat du calcul CMP
            $table->decimal('cmp_after', 10, 4)->default(0);
            // Stock du produit AVANT cet achat — pour recalculs éventuels
            $table->decimal('stock_before', 10, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};

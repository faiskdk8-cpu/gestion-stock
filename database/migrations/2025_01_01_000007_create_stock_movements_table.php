<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Type de mouvement : achat (entrée) ou vente (sortie)
            $table->enum('type', ['purchase', 'sale']);
            // Direction du mouvement sur le stock
            $table->enum('direction', ['+', '-']);
            // Quantité du mouvement
            $table->decimal('quantity', 10, 4);
            // Stock après ce mouvement — pour reconstituer l'historique
            $table->decimal('stock_after', 10, 4);
            // CMP après ce mouvement (inchangé pour les ventes)
            $table->decimal('cmp_after', 10, 4);
            // Référence polymorphique vers purchase_items ou sale_items
            $table->nullableMorphs('moveable');
            // Date réelle de l'opération (peut différer de created_at)
            $table->date('date');
            $table->timestamps();

            // Index pour accélérer les requêtes d'historique par produit et par date
            $table->index(['product_id', 'date']);
            $table->index(['product_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

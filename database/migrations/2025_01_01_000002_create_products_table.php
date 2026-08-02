<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            // Unité de mesure : kg, litre, pièce, sachet, etc.
            $table->string('unit', 50);
            // Seuil déclenchant l'alerte de stock faible (0 = alerte désactivée)
            $table->decimal('alert_threshold', 10, 4)->default(0);
            // Quantité disponible — mise à jour automatique par les achats/ventes
            $table->decimal('current_stock', 10, 4)->default(0);
            // Coût Moyen Pondéré courant — recalculé à chaque achat (4 décimales pour précision)
            $table->decimal('current_cmp', 10, 4)->default(0);
            // 1 = actif (visible dans les formulaires), 0 = archivé
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

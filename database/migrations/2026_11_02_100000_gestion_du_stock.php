<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Produits : stock et coût de revient ---
        Schema::table('produits', function (Blueprint $table) {
            $table->decimal('cout_moyen_fcfa', 12, 4)->nullable()->after('cout_fcfa'); // coût moyen pondéré d'une unité
            $table->unsignedInteger('unites_par_lot')->default(1)->after('cout_moyen_fcfa'); // ex. 24 biscuits par paquet
            $table->integer('stock_actuel')->default(0)->after('unites_par_lot');          // en unités
            $table->unsignedInteger('seuil_alerte')->nullable()->after('stock_actuel');     // null = 1 paquet
            $table->boolean('suivre_stock')->default(false)->after('seuil_alerte');
        });
        DB::table('produits')->whereNotNull('cout_fcfa')->update(['cout_moyen_fcfa' => DB::raw('cout_fcfa')]);

        // --- Lignes de vente : le coût au moment de la vente (le bénéfice reste juste si les prix changent) ---
        Schema::table('lignes_vente', function (Blueprint $table) {
            $table->decimal('cout_unitaire_fcfa', 12, 4)->nullable()->after('prix_unitaire_fcfa');
        });
        // Anciennes ventes : on reprend le coût actuel du produit (approximation)
        DB::table('lignes_vente')
            ->join('produits', 'produits.id', '=', 'lignes_vente.produit_id')
            ->whereNotNull('produits.cout_fcfa')
            ->update(['lignes_vente.cout_unitaire_fcfa' => DB::raw('produits.cout_fcfa')]);

        // --- Achats chez le fournisseur ---
        Schema::create('approvisionnements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained('boutiques')->cascadeOnDelete();
            $table->string('fournisseur')->nullable();
            $table->unsignedBigInteger('depense_fcfa')->default(0);
            $table->timestamp('cree_le')->useCurrent();
        });

        Schema::create('lignes_approvisionnement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approvisionnement_id')->constrained('approvisionnements')->cascadeOnDelete();
            $table->foreignId('produit_id')->nullable()->constrained('produits')->nullOnDelete();
            $table->string('nom_produit');
            $table->unsignedInteger('nb_lots');
            $table->unsignedInteger('unites_par_lot');
            $table->unsignedInteger('prix_lot_fcfa');
            $table->decimal('cout_unitaire_fcfa', 12, 4);
        });

        // --- Journal de chaque entrée et sortie de stock ---
        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained('boutiques')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->string('type', 30); // achat | vente | correction | stock_initial
            $table->integer('quantite'); // + entrée, - sortie (en unités)
            $table->integer('stock_apres');
            $table->string('reference_type', 30)->nullable(); // vente | approvisionnement
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('cree_le')->useCurrent();
            $table->index(['produit_id', 'cree_le']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock');
        Schema::dropIfExists('lignes_approvisionnement');
        Schema::dropIfExists('approvisionnements');
        Schema::table('lignes_vente', fn (Blueprint $t) => $t->dropColumn('cout_unitaire_fcfa'));
        Schema::table('produits', fn (Blueprint $t) => $t->dropColumn(
            ['cout_moyen_fcfa', 'unites_par_lot', 'stock_actuel', 'seuil_alerte', 'suivre_stock']
        ));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
            $table->enum('methode', ['especes', 'lightning', 'wave', 'orange_money', 'free_money']);
            $table->unsignedInteger('montant_fcfa');
            $table->unsignedBigInteger('montant_sats')->nullable();
            $table->decimal('taux_btc_fcfa', 18, 4)->nullable();
            $table->string('reference_prestataire')->nullable()->unique();
            $table->enum('statut', ['en_attente', 'paye', 'expire', 'echoue'])->default('en_attente');
            $table->timestamp('expire_le')->nullable();
            $table->timestamp('paye_le')->nullable();
            $table->timestamp('cree_le')->nullable();
            $table->timestamp('modifie_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};

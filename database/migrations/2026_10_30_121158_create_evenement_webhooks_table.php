<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenements_webhook', function (Blueprint $table) {
            $table->id();
            $table->enum('prestataire', ['lightning', 'paydunya']);
            $table->string('evenement_id');
            $table->json('donnees');
            $table->timestamp('traite_le')->nullable();
            $table->timestamp('cree_le')->nullable();
            $table->unique(['prestataire', 'evenement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenements_webhook');
    }
};

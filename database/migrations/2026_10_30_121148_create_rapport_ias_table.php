<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapports_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained('boutiques')->cascadeOnDelete();
            $table->date('periode_debut');
            $table->date('periode_fin');
            $table->text('contenu');
            $table->timestamp('cree_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports_ia');
    }
};

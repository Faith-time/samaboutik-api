<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taux_change', function (Blueprint $table) {
            $table->id();
            $table->decimal('btc_fcfa', 18, 4);
            $table->timestamp('recupere_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taux_change');
    }
};

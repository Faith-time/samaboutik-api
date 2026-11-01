<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            if (!Schema::hasColumn('paiements', 'facture_lightning')) {
                $table->text('facture_lightning')->nullable()->after('reference_prestataire');
            }
            if (!Schema::hasColumn('paiements', 'url_paiement')) {
                $table->text('url_paiement')->nullable()->after('facture_lightning');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            if (Schema::hasColumn('paiements', 'url_paiement')) {
                $table->dropColumn('url_paiement');
            }
            if (Schema::hasColumn('paiements', 'facture_lightning')) {
                $table->dropColumn('facture_lightning');
            }
        });
    }
};

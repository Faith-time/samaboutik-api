<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    protected $table = 'paiements';
    const CREATED_AT = 'cree_le';
    const UPDATED_AT = 'modifie_le';

    protected $fillable = [
        'vente_id', 'methode', 'montant_fcfa', 'montant_sats', 'taux_btc_fcfa',
        'reference_prestataire', 'facture_lightning', 'url_paiement',
        'statut', 'expire_le', 'paye_le',
    ];
    protected $casts = [
        'montant_fcfa' => 'integer',
        'montant_sats' => 'integer',
        'taux_btc_fcfa' => 'decimal:4',
        'expire_le' => 'datetime',
        'paye_le' => 'datetime',
    ];

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class, 'vente_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneVente extends Model
{
    protected $table = 'lignes_vente';
    public $timestamps = false;

    protected $fillable = ['vente_id', 'produit_id', 'nom_produit', 'prix_unitaire_fcfa', 'quantite'];
    protected $casts = [
        'prix_unitaire_fcfa' => 'integer',
        'quantite' => 'integer',
    ];

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class, 'vente_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}

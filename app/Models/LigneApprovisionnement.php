<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LigneApprovisionnement extends Model
{
    protected $table = 'lignes_approvisionnement';
    public $timestamps = false;

    protected $fillable = [
        'approvisionnement_id', 'produit_id', 'nom_produit',
        'nb_lots', 'unites_par_lot', 'prix_lot_fcfa', 'cout_unitaire_fcfa',
    ];
    protected $casts = [
        'nb_lots' => 'integer',
        'unites_par_lot' => 'integer',
        'prix_lot_fcfa' => 'integer',
        'cout_unitaire_fcfa' => 'float',
    ];
}

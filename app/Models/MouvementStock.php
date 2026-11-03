<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MouvementStock extends Model
{
    protected $table = 'mouvements_stock';
    const CREATED_AT = 'cree_le';
    const UPDATED_AT = null;

    protected $fillable = [
        'boutique_id', 'produit_id', 'type', 'quantite', 'stock_apres',
        'reference_type', 'reference_id', 'note',
    ];
    protected $casts = ['quantite' => 'integer', 'stock_apres' => 'integer'];
}

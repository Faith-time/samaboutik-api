<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produit extends Model
{
    protected $table = 'produits';
    const CREATED_AT = 'cree_le';
    const UPDATED_AT = 'modifie_le';

    protected $fillable = ['boutique_id', 'nom', 'prix_fcfa', 'cout_fcfa', 'actif'];
    protected $casts = [
        'prix_fcfa' => 'integer',
        'cout_fcfa' => 'integer',
        'actif' => 'boolean',
    ];

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }
}

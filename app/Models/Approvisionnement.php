<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Approvisionnement extends Model
{
    protected $table = 'approvisionnements';
    const CREATED_AT = 'cree_le';
    const UPDATED_AT = null;

    protected $fillable = ['boutique_id', 'fournisseur', 'depense_fcfa'];
    protected $casts = ['depense_fcfa' => 'integer'];

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneApprovisionnement::class, 'approvisionnement_id');
    }
}

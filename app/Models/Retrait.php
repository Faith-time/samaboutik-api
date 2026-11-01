<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Retrait extends Model
{
    protected $table = 'retraits';
    const CREATED_AT = 'cree_le';
    const UPDATED_AT = null;

    protected $fillable = ['boutique_id', 'montant_fcfa', 'operateur'];
    protected $casts = ['montant_fcfa' => 'integer'];

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }
}

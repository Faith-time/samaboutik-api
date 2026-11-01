<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RapportIa extends Model
{
    protected $table = 'rapports_ia';
    const CREATED_AT = 'cree_le';
    const UPDATED_AT = null;

    protected $fillable = ['boutique_id', 'periode_debut', 'periode_fin', 'contenu'];
    protected $casts = [
        'periode_debut' => 'date',
        'periode_fin' => 'date',
    ];

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }
}

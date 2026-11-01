<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvenementWebhook extends Model
{
    protected $table = 'evenements_webhook';
    const CREATED_AT = 'cree_le';
    const UPDATED_AT = null;

    protected $fillable = ['prestataire', 'evenement_id', 'donnees', 'traite_le'];
    protected $casts = [
        'donnees' => 'array',
        'traite_le' => 'datetime',
    ];
}

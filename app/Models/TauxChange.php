<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TauxChange extends Model
{
    protected $table = 'taux_change';
    public $timestamps = false;

    protected $fillable = ['btc_fcfa', 'recupere_le'];
    protected $casts = [
        'btc_fcfa' => 'decimal:4',
        'recupere_le' => 'datetime',
    ];
}

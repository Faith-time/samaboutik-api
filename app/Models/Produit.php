<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produit extends Model
{
    protected $table = 'produits';
    const CREATED_AT = 'cree_le';
    const UPDATED_AT = 'modifie_le';

    protected $fillable = [
        'boutique_id', 'nom', 'prix_fcfa', 'cout_fcfa', 'cout_moyen_fcfa', 'actif',
        'unites_par_lot', 'stock_actuel', 'seuil_alerte', 'suivre_stock',
    ];
    protected $casts = [
        'prix_fcfa' => 'integer',
        'cout_fcfa' => 'integer',
        'cout_moyen_fcfa' => 'float',
        'actif' => 'boolean',
        'unites_par_lot' => 'integer',
        'stock_actuel' => 'integer',
        'seuil_alerte' => 'integer',
        'suivre_stock' => 'boolean',
    ];
    // Ces champs calculés sont ajoutés automatiquement au JSON
    protected $appends = ['statut_stock', 'lots_restants', 'seuil_effectif'];

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    // Seuil d'alerte : celui du commerçant, sinon 1 paquet
    public function getSeuilEffectifAttribute(): int
    {
        return $this->seuil_alerte ?? max((int) $this->unites_par_lot, 1);
    }

    // non_suivi | ok | bas | epuise
    public function getStatutStockAttribute(): string
    {
        if (! $this->suivre_stock) {
            return 'non_suivi';
        }
        if ($this->stock_actuel <= 0) {
            return 'epuise';
        }

        return $this->stock_actuel <= $this->seuil_effectif ? 'bas' : 'ok';
    }

    public function getLotsRestantsAttribute(): ?float
    {
        if (! $this->suivre_stock) {
            return null;
        }

        return round(max($this->stock_actuel, 0) / max((int) $this->unites_par_lot, 1), 1);
    }
}

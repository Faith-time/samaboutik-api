<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Utilisateur extends Authenticatable
{
    use HasApiTokens, HasRoles;

    protected $table = 'utilisateurs';
    const CREATED_AT = 'cree_le';
    const UPDATED_AT = 'modifie_le';

    // Garde utilisée par Spatie pour les rôles (la même partout)
    protected $guard_name = 'web';

    protected $fillable = ['nom', 'telephone', 'mot_de_passe'];
    protected $hidden = ['mot_de_passe'];
    protected $casts = ['mot_de_passe' => 'hashed'];

    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    // Une seule boutique par commerçant (les clients et l'admin n'en ont pas)
    public function boutique(): HasOne
    {
        return $this->hasOne(Boutique::class, 'utilisateur_id');
    }
}

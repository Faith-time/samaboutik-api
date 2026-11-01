<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TauxChange;

class TauxChangeController extends Controller
{
    public function dernier()
    {
        $taux = TauxChange::orderByDesc('recupere_le')->first();

        abort_if(! $taux, 404, 'Aucun taux disponible.');

        return $taux;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Le client n'a pas de compte : il paie avec son propre portefeuille.
    // Toute inscription depuis l'application crée donc un commerçant.
    // Le rôle « admin » ne s'obtient jamais depuis l'application.
    public function inscription(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:20', 'unique:utilisateurs,telephone'],
            'mot_de_passe' => ['required', 'string', 'min:6'],
            'nom_boutique' => ['required', 'string', 'max:255'],
        ]);

        $utilisateur = DB::transaction(function () use ($data) {
            $utilisateur = Utilisateur::create([
                'nom' => $data['nom'],
                'telephone' => $data['telephone'],
                'mot_de_passe' => $data['mot_de_passe'],
            ]);
            $utilisateur->assignRole('commercant');
            $utilisateur->boutique()->create(['nom' => $data['nom_boutique']]);

            return $utilisateur;
        });

        return $this->reponse($utilisateur, 201);
    }

    public function connexion(Request $request)
    {
        $data = $request->validate([
            'telephone' => ['required', 'string'],
            'mot_de_passe' => ['required', 'string'],
        ]);

        $utilisateur = Utilisateur::where('telephone', $data['telephone'])->first();

        if (! $utilisateur || ! Hash::check($data['mot_de_passe'], $utilisateur->mot_de_passe)) {
            throw ValidationException::withMessages([
                'telephone' => ['Téléphone ou mot de passe incorrect.'],
            ]);
        }

        return $this->reponse($utilisateur);
    }

    public function moi(Request $request)
    {
        return response()->json($this->versTableau($request->user()));
    }

    public function deconnexion(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    private function reponse(Utilisateur $utilisateur, int $code = 200)
    {
        return response()->json([
            'token' => $utilisateur->createToken('mobile')->plainTextToken,
            'utilisateur' => $this->versTableau($utilisateur),
        ], $code);
    }

    // L'utilisateur avec sa boutique et un champ simple « role » (commercant | admin)
    private function versTableau(Utilisateur $utilisateur): array
    {
        $utilisateur->load(['boutique', 'roles']);
        $role = $utilisateur->getRoleNames()->first();

        return array_merge($utilisateur->makeHidden('roles')->toArray(), ['role' => $role]);
    }
}

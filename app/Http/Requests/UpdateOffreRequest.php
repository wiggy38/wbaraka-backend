<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOffreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom_produit'              => ['required', 'string', 'max:255'],
            'taux_interet_mensuel'     => ['required', 'numeric', 'min:0'],
            'montant_min'              => ['required', 'integer', 'min:0'],
            'montant_max'              => ['required', 'integer', 'min:0', 'gte:montant_min'],
            'duree_min_mois'           => ['required', 'integer', 'min:1'],
            'duree_max_mois'           => ['required', 'integer', 'min:1', 'gte:duree_min_mois'],
            'frais_dossier'            => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'assurance'                => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'garantie_requise'         => ['required', 'in:aucune,caution,neant,bien'],
            'delai_traitement_jours'   => ['required', 'integer', 'min:0'],
            'cible_specifique'         => ['sometimes', 'nullable', 'array'],
            'cible_specifique.*'       => ['string'],
            'zones_couverture'         => ['required', 'array', 'min:1'],
            'zones_couverture.*'       => ['string'],
            'statut'                   => ['required', 'in:brouillon,en_validation,actif,inactif'],
        ];
    }
}

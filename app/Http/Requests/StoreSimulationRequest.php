<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'montant_emprunte' => ['required', 'integer', 'min:25000', 'max:10000000'],
            'duree_mois'       => ['required', 'integer', 'in:1,3,6,12,18,24'],
            'taux_utilise'     => ['required', 'numeric', 'min:0'],
            'id_offre'         => ['nullable', 'uuid', 'exists:offres,id_offre'],
            'frais_dossier'    => ['nullable', 'numeric', 'min:0'],
            'assurance'        => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfilImfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom'              => ['required', 'string', 'max:255'],
            'description'      => ['sometimes', 'nullable', 'string'],
            'zones_couverture' => ['required', 'array', 'min:1'],
            'zones_couverture.*' => ['string'],
            'email_contact'    => ['required', 'email', 'max:255'],
            'telephone'        => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }
}

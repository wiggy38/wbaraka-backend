<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateImfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom'              => ['required', 'string', 'max:255'],
            'email_contact'    => ['required', 'email', 'max:255'],
            'telephone'        => ['nullable', 'string', 'max:30'],
            'description'      => ['nullable', 'string'],
            'zones_couverture' => ['required', 'array', 'min:1'],
            'zones_couverture.*' => ['string'],
            'logo_url'         => ['nullable', 'url', 'max:500'],

            'agent_nom'        => ['required', 'string', 'max:255'],
            'agent_email'      => ['required', 'email', 'max:255', 'unique:agents,email'],
            'agent_password'   => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'zones_couverture.required' => 'Au moins une zone de couverture est requise.',
            'zones_couverture.min'      => 'Au moins une zone de couverture est requise.',
            'agent_email.unique'        => 'Cet email est déjà utilisé par un agent existant.',
        ];
    }
}

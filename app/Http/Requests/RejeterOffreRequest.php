<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejeterOffreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motif.required' => 'Le motif de rejet est obligatoire.',
            'motif.min'      => 'Le motif doit contenir au moins 10 caractères.',
        ];
    }
}

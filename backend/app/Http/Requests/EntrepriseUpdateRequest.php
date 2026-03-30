<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EntrepriseUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'client_user_id'  => ['sometimes', 'nullable', 'exists:users,id'],
            'raison_sociale'  => ['sometimes', 'required', 'string', 'max:255'],
            'forme_juridique' => ['sometimes', 'required', 'string', 'max:100'],
            'adresse'         => ['sometimes', 'required', 'string'],
            'ville'           => ['sometimes', 'required', 'string', 'max:100'],
            'pays'            => ['sometimes', 'required', 'string', 'max:100'],
            'capital'         => ['sometimes', 'required', 'numeric', 'min:0'],
            'date_creation'   => ['sometimes', 'required', 'date'],
            'statut'          => ['sometimes', 'required', 'string', 'max:50'],
        ];
    }
}

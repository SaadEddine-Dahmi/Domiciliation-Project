<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EntrepriseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'client_user_id'  => ['nullable', 'exists:users,id'], // optional for now
            'raison_sociale'  => ['required', 'string', 'max:255'],
            'forme_juridique' => ['required', 'string', 'max:100'],
            'adresse'         => ['required', 'string'],
            'ville'           => ['required', 'string', 'max:100'],
            'pays'            => ['required', 'string', 'max:100'],
            'capital'         => ['required', 'numeric', 'min:0'],
            'date_creation'   => ['required', 'date'],
            'statut'          => ['required', 'string', 'max:50'],
        ];
    }
}

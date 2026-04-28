<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntrepriseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'domiciliataire_id' => $this->domiciliataire_id,
            'client_user_id'    => $this->client_user_id,
            'raison_sociale'    => $this->raison_sociale,
            'forme_juridique'   => $this->forme_juridique,
            'adresse'           => $this->adresse,
            'ville'             => $this->ville,
            'pays'              => $this->pays,
            'capital'           => $this->capital,
            'date_creation'     => optional($this->date_creation)->format('Y-m-d'),
            'statut'            => $this->statut,
            'created_at'        => optional($this->created_at)->toISOString(),
            'updated_at'        => optional($this->updated_at)->toISOString(),

            // Only included when eager loaded with ->with('representant')
            'representant'      => $this->whenLoaded('representant'),

            // Only included when eager loaded with ->with('clientUser')
            'client_user'       => $this->whenLoaded('clientUser', fn() => [
                'id'        => $this->clientUser->id,
                'nom'       => $this->clientUser->nom,
                'prenom'    => $this->clientUser->prenom,
                'email'     => $this->clientUser->email,
                'telephone' => $this->clientUser->telephone,
            ]),
        ];
    }
}

<?php
// app/Services/Contracts/ContractTokenResolver.php
// Resolves contract PDF template tokens from loaded contract relations.

namespace App\Services\Contracts;

use App\Models\Contrat;
use Carbon\Carbon;

class ContractTokenResolver
{
    public function map(Contrat $contrat): array
    {
        $entreprise = $contrat->entreprise;
        $clientRep = $entreprise?->representant;
        $domiciliataire = $contrat->domiciliataire;
        $domiciliataireRep = $domiciliataire?->representant;
        $profile = $domiciliataire?->profile;

        $money = static fn($value): string => $value !== null
            ? number_format((float) $value, 2, ',', ' ') . ' DH'
            : '';

        $date = static fn($value): string => $value
            ? Carbon::parse($value)->format('d/m/Y')
            : '';

        $addresses = collect($profile?->adresses_list ?? [])
            ->map(static fn($address): string => trim(($address['label'] ?? '') . ' : ' . ($address['value'] ?? '')))
            ->filter()
            ->implode(' - ');

        return [
            'domiciliataire_nom' => $profile?->nom_societe ?? '',
            'domiciliataire_rc' => $profile?->rc ?? '',
            'domiciliataire_if' => $profile?->if_fiscal ?? '',
            'domiciliataire_tp' => $profile?->tp ?? '',
            'domiciliataire_siege_succursales' => $addresses,
            'domiciliataire_representant' => $domiciliataireRep?->nom_complet ?? '',
            'domiciliataire_cin' => $domiciliataireRep?->cin ?? '',
            'raison_sociale' => $entreprise?->raison_sociale ?? '',
            'societe' => $entreprise?->raison_sociale ?? '',
            'forme_juridique' => $entreprise?->forme_juridique ?? '',
            'adresse_domiciliation' => $entreprise?->adresse ?? '',
            'ville_client' => $entreprise?->ville ?? '',
            'gerant_nom' => trim(($clientRep?->nom ?? '') . ' ' . ($clientRep?->prenom ?? '')),
            'gerant_prenom' => $clientRep?->prenom ?? '',
            'gerant_identite' => $clientRep?->cin ?? '',
            'gerant_cin' => $clientRep?->cin ?? '',
            'gerant_telephone' => $clientRep?->telephone ?? '',
            'telephone' => $clientRep?->telephone ?? '',
            'gerant_email' => $clientRep?->email ?? '',
            'email' => $clientRep?->email ?? '',
            'gerant_adresse' => $clientRep?->adresse ?? '',
            'gerant_nationalite' => $clientRep?->nationalite ?? '',
            'date_naissance' => $date($clientRep?->date_naissance),
            'date_debut' => $date($contrat->date_debut),
            'date_fin' => $date($contrat->date_fin),
            'date_signature' => $date($contrat->date_signature),
            'duree_mois' => (string) ($contrat->duree_mois ?? ''),
            'instruction_no' => $contrat->instruction_no ?? '',
            'ville_signature' => $contrat->ville_signature ?? '',
            'prix_mensuel' => $money($contrat->prix_mensuel),
            'prix_total' => $money($contrat->prix_total),
            'caution' => $money($contrat->caution),
            'mode_paiement' => $contrat->mode_paiement ?? '',
            'redevance_mensuelle' => $money($contrat->prix_mensuel),
            'redevance_annuelle' => $money($contrat->prix_total),
        ];
    }

    public function resolve(string $text, array $tokens): string
    {
        if ($text === '' || $tokens === []) {
            return $text;
        }

        $replace = [];
        foreach ($tokens as $key => $value) {
            $replace[strtolower($key)] = (string) $value;
        }

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', static function ($match) use ($replace) {
            $normalized = strtolower($match[1]);

            return $replace[$normalized] ?? $match[0];
        }, $text);
    }
}

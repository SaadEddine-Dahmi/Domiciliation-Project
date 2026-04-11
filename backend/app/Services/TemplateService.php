<?php

namespace App\Services;

use App\Models\Contrat;

class TemplateService
{
    /**
     * Replace {{variable}} placeholders with real values.
     * Each replaced value is wrapped in <strong> so it
     * appears bold in the PDF — matching the contract style.
     *
     * @param string $content  Article body with {{placeholders}}
     * @param array  $data     ['variable_key' => 'real value']
     * @return string          Rendered HTML
     */
    public function render(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            $content = str_replace(
                '{{' . $key . '}}',
                '<strong>' . e((string) ($value ?? '')) . '</strong>',
                $content
            );
        }

        // Clean up any unreplaced variables — show them visibly
        // so the domiciliataire knows which data is missing
        $content = preg_replace_callback(
            '/\{\{([a-z_]+)\}\}/',
            fn($m) => '<span style="color:#c8a96e;font-style:italic">[' . $m[1] . ']</span>',
            $content
        );

        return $content;
    }

    /**
     * Build the full variable map from a Contrat instance.
     * Covers ALL variables available in the VariablePanel component.
     */
    public function dataFromContrat(Contrat $contrat): array
    {
        $entreprise        = $contrat->entreprise;
        $representant      = $entreprise?->representant;
        $domiciliataire    = $contrat->domiciliataire;

        return [
            // ── Domiciliataire ─────────────────────────────
            'domiciliataire_nom'     => trim(($domiciliataire?->nom ?? '') . ' ' . ($domiciliataire?->prenom ?? '')),
            'domiciliataire_rc'      => $domiciliataire?->rc      ?? '',
            'domiciliataire_if'      => $domiciliataire?->if_number ?? '',
            'domiciliataire_adresse' => $domiciliataire?->adresse ?? '',

            // ── Entreprise ─────────────────────────────────
            'raison_sociale'     => $entreprise?->raison_sociale    ?? '',
            'forme_juridique'    => $entreprise?->forme_juridique   ?? '',
            'adresse_entreprise' => $entreprise?->adresse           ?? '',
            'ville'              => $entreprise?->ville             ?? '',
            'pays'               => $entreprise?->pays              ?? '',
            'capital'            => $entreprise?->capital
                ? number_format((float) $entreprise->capital, 2, '.', ' ') . ' DH'
                : '',

            // ── Représentant ───────────────────────────────
            'gerant_nom'         => $representant?->nom_complet                                          ?? '',
            'gerant_cin'         => $representant?->cin                                                  ?? '',
            'gerant_naissance'   => optional($representant?->date_naissance)->format('d/m/Y')            ?? '',
            'gerant_adresse'     => $representant?->adresse                                              ?? '',
            'gerant_telephone'   => $representant?->telephone                                            ?? '',
            'gerant_email'       => $representant?->email                                                ?? '',
            'gerant_nationalite' => $representant?->nationalite                                          ?? '',

            // ── Contrat ────────────────────────────────────
            'numero_contrat'   => (string) ($contrat->id             ?? ''),
            'instruction_no'   => $contrat->instruction_no           ?? '',
            'date_debut'       => optional($contrat->date_debut)->format('d/m/Y')       ?? '',
            'date_fin'         => optional($contrat->date_fin)->format('d/m/Y')         ?? '',
            'duree_mois'       => (string) ($contrat->duree_mois     ?? ''),
            'date_signature'   => optional($contrat->date_signature)->format('d/m/Y')   ?? '',
            'ville_signature'  => $contrat->ville_signature          ?? '',

            // ── Financier ──────────────────────────────────
            'redevance_mensuelle' => $contrat->prix_mensuel
                ? number_format((float) $contrat->prix_mensuel, 2, '.', ' ') . ' DH'
                : '',
            'redevance_annuelle'  => $contrat->prix_total
                ? number_format((float) $contrat->prix_total, 2, '.', ' ') . ' DH'
                : '',
            'caution'             => $contrat->caution
                ? number_format((float) $contrat->caution, 2, '.', ' ') . ' DH'
                : '',
            'mode_paiement'       => $contrat->mode_paiement ?? '',
        ];
    }
}
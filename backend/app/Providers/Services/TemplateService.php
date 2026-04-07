<?php

namespace App\Services;

use App\Models\Contrat;

class TemplateService
{
    /**
     * Replace {{variable}} placeholders in article content.
     * Each replaced value is wrapped in <strong> to match
     * the bold dynamic values in the PDF contract.
     *
     * Usage:
     *   $html = $templateService->render($article->body, $data);
     *
     * @param string $content  Raw article body with {{placeholders}}
     * @param array  $data     ['variable_name' => 'value']
     * @return string          Rendered HTML
     */
    public function render(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            $content = str_replace(
                '{{' . $key . '}}',
                '<strong>' . e((string) $value) . '</strong>',
                $content
            );
        }

        return $content;
    }

    /**
     * Build the variable map from a Contrat instance.
     * Covers all {{variables}} detected in the PDF contract.
     *
     * Variables available:
     *   {{instruction_no}}      — contrat number (e.g. 1923)
     *   {{date_debut}}          — start date
     *   {{date_fin}}            — end date
     *   {{ville_signature}}     — signing city
     *   {{raison_sociale}}      — company name
     *   {{gerant_nom}}          — full name of manager
     *   {{gerant_cin}}          — CIN number
     *   {{gerant_naissance}}    — date of birth
     *   {{gerant_adresse}}      — personal address
     *   {{gerant_telephone}}    — phone number
     *   {{gerant_email}}        — email address
     *   {{redevance_mensuelle}} — monthly fee
     *   {{redevance_annuelle}}  — annual fee (prix_total)
     */
    public function dataFromContrat(Contrat $contrat): array
    {
        $entreprise   = $contrat->entreprise;
        $representant = $entreprise?->representant;

        return [
            'instruction_no'      => $contrat->instruction_no ?? '',
            'date_debut'          => optional($contrat->date_debut)->format('d/m/Y') ?? '',
            'date_fin'            => optional($contrat->date_fin)->format('d/m/Y') ?? '',
            'ville_signature'     => $contrat->ville_signature ?? '',
            'raison_sociale'      => $entreprise?->raison_sociale ?? '',
            'gerant_nom'          => $representant?->nom_complet ?? '',
            'gerant_cin'          => $representant?->cin ?? '',
            'gerant_naissance'    => optional($representant?->date_naissance)->format('d/m/Y') ?? '',
            'gerant_adresse'      => $representant?->adresse ?? '',
            'gerant_telephone'    => $representant?->telephone ?? '',
            'gerant_email'        => $representant?->email ?? '',
            'redevance_mensuelle' => number_format((float)($contrat->prix_mensuel ?? 0), 2, '.', '') . ' DH',
            'redevance_annuelle'  => number_format((float)($contrat->prix_total ?? 0), 2, '.', '') . ' DH',
        ];
    }
}

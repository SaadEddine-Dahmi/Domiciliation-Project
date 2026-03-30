<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Article;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            ['title' => 'Durée du contrat',             'body' => 'Le présent contrat prend effet à la date de signature et demeure valable pour la période convenue entre les parties.'],
            ['title' => 'Définition des prestations',   'body' => 'Le domiciliataire assure les services de domiciliation selon les conditions fixées par le présent contrat.'],
            ['title' => 'Fonctionnement du service',    'body' => 'Le traitement du courrier et des notifications suit les modalités convenues entre les parties.'],
            ['title' => 'Renouvellement',                'body' => 'Le contrat peut être renouvelé conformément aux clauses convenues.'],
            ['title' => 'Exactitude des renseignements','body' => "Le domicilié garantit l'exactitude des informations et documents communiqués."],
            ['title' => 'Résiliation',                   'body' => 'Le contrat peut être résilié selon les conditions de préavis et motifs prévus.'],
            ['title' => 'Obligations du domicilié',     'body' => "Le domicilié s'engage à respecter les obligations légales et administratives applicables."],
            ['title' => 'Responsabilité',                'body' => 'Chaque partie assume la responsabilité de ses engagements et de ses actes.'],
            ['title' => 'Clause résolutoire',            'body' => 'En cas de manquement grave, le contrat peut être résilié de plein droit.'],
            ['title' => 'Frais et tribunal compétent',  'body' => 'Les frais sont supportés par la partie concernée. En cas de litige, le tribunal compétent est celui du siège social du domiciliataire.'],
            ['title' => 'Mandat de réception',           'body' => 'Le domiciliataire est mandaté pour la réception des correspondances dans les limites prévues.'],
            ['title' => 'Redevance mensuelle / annuelle','body' => 'La redevance est définie mensuellement avec conversion automatique sur la période contractuelle.'],
            ['title' => 'Contact et coordonnées',        'body' => 'Les notifications officielles sont envoyées aux coordonnées renseignées dans ce contrat.'],
        ];

        foreach ($articles as $data) {
            Article::firstOrCreate(
                ['title' => $data['title']],
                ['body' => $data['body'], 'is_active' => true]
            );
        }
    }
}

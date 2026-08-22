<?php
// database/seeders/DefaultArticlesSeeder.php
//
// Seeds the two clauses that used to be hardcoded in contrat.blade.php
// (REDEVANCE and CONTACT) as ordinary, editable Article rows for a given
// domiciliataire. Run once per new domiciliataire account so their clause
// library starts non-empty and the wizard behaves consistently — the
// domiciliataire can freely edit or delete these afterwards like any
// other article, since they're no longer special-cased anywhere.

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

class DefaultArticlesSeeder extends Seeder
{
    public function run(int $domiciliataireId): void
    {
        Article::create([
            'domiciliataire_id' => $domiciliataireId,
            'title' => 'Redevance',
            'body' => "Le présent contrat est consenti moyennant une redevance mensuelle de {{redevance_mensuelle}}, soit {{redevance_annuelle}} Annuelle payable d'avance.",
            'is_active' => true,
        ]);

        Article::create([
            'domiciliataire_id' => $domiciliataireId,
            'title' => 'Contact',
            'body' => "Je certifie, {{gerant_nom}} l'exactitude des informations ci-dessous :\nN° Tel : {{gerant_telephone}}\nEmail : {{gerant_email}}\nAdresse personnelle : {{gerant_adresse}}",
            'is_active' => true,
        ]);
    }
}
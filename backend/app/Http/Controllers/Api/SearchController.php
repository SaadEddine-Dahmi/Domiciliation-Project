<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Document;
use App\Models\Entreprise;
use App\Models\Facture;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = auth()->user();
        $term = trim((string) $request->query('q', ''));

        if ($term === '' || mb_strlen($term) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'success' => true,
                'data' => Entreprise::query()
                    ->where('raison_sociale', 'like', "%{$term}%")
                    ->limit(8)
                    ->get(['id', 'raison_sociale'])
                    ->map(fn($client) => [
                        'type' => 'client',
                        'title' => $client->raison_sociale,
                        'subtitle' => 'Client',
                        'to' => '/admin/clients/' . $client->id,
                    ]),
            ]);
        }

        if ($user->role === 'client') {
            $entreprise = Entreprise::where('client_user_id', $user->id)->first();
            if (!$entreprise) {
                return response()->json(['success' => true, 'data' => []]);
            }

            return response()->json([
                'success' => true,
                'data' => collect([
                    ['type' => 'contrat', 'title' => 'Mon contrat', 'subtitle' => $entreprise->raison_sociale, 'to' => '/client/contrat'],
                    ['type' => 'documents', 'title' => 'Mes documents', 'subtitle' => $entreprise->raison_sociale, 'to' => '/client/documents'],
                    ['type' => 'messages', 'title' => 'Messages', 'subtitle' => 'Communication', 'to' => '/client/messages'],
                ])->filter(fn($item) => str_contains(mb_strtolower($item['title'] . ' ' . $item['subtitle']), mb_strtolower($term)))->values(),
            ]);
        }

        if ($user->role !== 'domiciliataire') {
            return response()->json(['success' => true, 'data' => []]);
        }

        $clients = Entreprise::query()
            ->where('domiciliataire_id', $user->id)
            ->where('raison_sociale', 'like', "%{$term}%")
            ->limit(5)
            ->get(['id', 'raison_sociale', 'ville'])
            ->map(fn($client) => [
                'type' => 'client',
                'title' => $client->raison_sociale,
                'subtitle' => $client->ville ?? 'Client',
                'to' => '/admin/clients/' . $client->id,
            ]);

        $contrats = Contrat::query()
            ->where('domiciliataire_id', $user->id)
            ->where(function ($query) use ($term) {
                $query->where('titre_contrat', 'like', "%{$term}%")
                    ->orWhere('instruction_no', 'like', "%{$term}%");
            })
            ->limit(5)
            ->get(['id', 'titre_contrat', 'instruction_no'])
            ->map(fn($contrat) => [
                'type' => 'contrat',
                'title' => $contrat->titre_contrat ?? "Contrat #{$contrat->id}",
                'subtitle' => $contrat->instruction_no ?? 'Contrat',
                'to' => '/admin/contrats',
            ]);

        $factures = Facture::query()
            ->where('domiciliataire_id', $user->id)
            ->where('numero_facture', 'like', "%{$term}%")
            ->limit(5)
            ->get(['id', 'numero_facture', 'montant_total'])
            ->map(fn($facture) => [
                'type' => 'facture',
                'title' => $facture->numero_facture ?? "Facture #{$facture->id}",
                'subtitle' => number_format((float) $facture->montant_total, 2, '.', ' ') . ' DH',
                'to' => '/admin/factures?facture_id=' . $facture->id,
            ]);

        $documents = Document::query()
            ->whereHas('entreprise', fn($query) => $query->where('domiciliataire_id', $user->id))
            ->whereHas('documentType', fn($query) => $query->where('name', 'like', "%{$term}%"))
            ->with(['documentType:id,name', 'entreprise:id,raison_sociale'])
            ->limit(5)
            ->get()
            ->map(fn($document) => [
                'type' => 'document',
                'title' => $document->documentType?->name ?? 'Document',
                'subtitle' => $document->entreprise?->raison_sociale ?? 'Document',
                'to' => '/admin/documents',
            ]);

        return response()->json([
            'success' => true,
            'data' => $clients->concat($contrats)->concat($factures)->concat($documents)->take(12)->values(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class FactureController extends Controller
{
    // Resolves a user from a ?token= query param — required for PDF
    // endpoints opened directly in the browser, which cannot set an
    // Authorization header on plain navigation.
    private function authenticateViaToken(Request $request): ?\App\Models\User
    {
        $value = $request->query('token');
        if (!$value)
            return null;
        $token = PersonalAccessToken::findToken($value);
        if (!$token)
            return null;
        if ($token->expires_at && $token->expires_at->isPast())
            return null;
        return $token->tokenable;
    }

    // Resolves an invoice by ID, tenant-scoped to the given user, with
    // all relations needed for PDF rendering eager-loaded.
    private function resolveFacture(int $id, \App\Models\User $user): ?Facture
    {
        return Facture::with(['entreprise', 'contrat', 'domiciliataire', 'paiements'])
            ->where('domiciliataire_id', $user->id)
            ->find($id);
    }

    // Lists all invoices for the authenticated domiciliataire.
    public function index()
    {
        $tenantId = auth()->id();

        $factures = Facture::query()
            ->where('domiciliataire_id', $tenantId)
            ->with([
                'entreprise:id,raison_sociale,adresse,ville,pays,forme_juridique',
                'contrat:id,date_debut,date_fin,statut,prix_total',
                'paiements',
            ])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $factures]);
    }

    // Generates the invoice PDF, authenticated via ?token= since this
    // URL is opened directly in a browser tab or iframe. ?mode=download
    // forces attachment disposition; default is inline preview.
    public function pdf(Request $request, int $id)
    {
        $user = $this->authenticateViaToken($request);
        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $facture = $this->resolveFacture($id, $user);
        if (!$facture) {
            return response()->json(['message' => 'Facture introuvable.'], 404);
        }

        $pdf = Pdf::loadView('pdf.facture', [
            'facture' => $facture,
            'entreprise' => $facture->entreprise,
            'contrat' => $facture->contrat,
            'domiciliataire' => $facture->domiciliataire,
            'paiements' => $facture->paiements,
            'totalPaye' => $facture->paiements->sum('montant'),
        ])->setPaper('a4', 'portrait');

        $filename = 'facture-' . ($facture->numero_facture ?? $facture->id) . '.pdf';
        $mode = $request->query('mode', 'preview');

        if ($mode === 'download') {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}
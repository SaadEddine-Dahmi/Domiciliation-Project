<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use Illuminate\Http\Request;

class FactureController extends Controller
{
    public function index()
    {
        $tenantId = auth()->id();

        // Get invoices where the associated contract belongs to the current user
        $factures = Facture::whereHas('contrat', function ($query) use ($tenantId) {
            $query->where('domiciliataire_id', $tenantId);
        })
            ->with(['entreprise:id,raison_sociale', 'contrat:id,reference'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $factures
        ]);
    }
}
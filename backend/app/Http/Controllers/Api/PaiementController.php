<?php
// app/Http/Controllers/Api/PaiementController.php
// Manages tenant-scoped payments recorded against contracts.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesApiRoles;
use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Paiement;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

class PaiementController extends Controller
{
    use AuthorizesApiRoles;

    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function index(int $contratId)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, 'domiciliataire', 'Accès interdit : les paiements appartiennent aux domiciliataires.')) {
            return $blocked;
        }

        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($contratId);
        $paiements = Paiement::query()
            ->whereHas('facture', fn($query) => $query->where('contrat_id', $contrat->id))
            ->with('facture:id,numero_facture,montant_total,statut,date_facture')
            ->latest('date_paiement')
            ->get();

        return response()->json(['success' => true, 'data' => $paiements]);
    }

    public function store(Request $request, int $contratId)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, 'domiciliataire', 'Accès interdit : les paiements appartiennent aux domiciliataires.')) {
            return $blocked;
        }

        $contrat = Contrat::where('domiciliataire_id', $user->id)
            ->whereIn('statut', ['active', 'draft'])
            ->with('entreprise:id,raison_sociale')
            ->findOrFail($contratId);
        $data = $request->validate($this->rules());

        $remaining = $contrat->prix_total !== null ? $this->payments->remainingBalance($contrat) : null;
        if ($remaining !== null && $data['montant'] > $remaining + 0.01) {
            return $this->overpaymentResponse($data['montant'], $remaining);
        }

        return response()->json([
            'success' => true,
            'data' => $this->payments->record($contrat, $user->id, $data)->load('facture'),
        ], 201);
    }

    public function summary(int $contratId)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, 'domiciliataire', 'Accès interdit : les paiements appartiennent aux domiciliataires.')) {
            return $blocked;
        }

        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($contratId);

        return response()->json(['success' => true, 'data' => $this->payments->summary($contrat)]);
    }

    private function rules(): array
    {
        return [
            'montant' => ['required', 'numeric', 'min:0.01'],
            'date_paiement' => ['required', 'date'],
            'mode_paiement' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function overpaymentResponse(float $amount, float $remaining): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => sprintf(
                'Le montant saisi (%s DH) dépasse le solde restant du contrat (%s DH).',
                number_format($amount, 2, ',', ' '),
                number_format($remaining, 2, ',', ' ')
            ),
        ], 422);
    }
}

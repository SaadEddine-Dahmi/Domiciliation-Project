<?php
// app/Http/Controllers/Api/FactureController.php
// Manages tenant-scoped invoice listing, PDFs, archive, restore, and deletion.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesApiRoles;
use App\Http\Controllers\Concerns\UsesApiPagination;
use App\Http\Controllers\Controller;
use App\Models\Facture;
use App\Models\User;
use App\Services\Auth\QueryTokenAuthenticator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FactureController extends Controller
{
    use AuthorizesApiRoles;
    use UsesApiPagination;

    public function __construct(private readonly QueryTokenAuthenticator $queryTokens)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, 'domiciliataire', 'Accès interdit : les factures appartiennent aux domiciliataires.')) {
            return $blocked;
        }

        $factures = Facture::query()
            ->where('domiciliataire_id', $user->id)
            ->when(!$request->boolean('include_archived'), fn($query) => $query->whereNull('archived_at'))
            ->with([
                'entreprise:id,raison_sociale,adresse,ville,pays,forme_juridique',
                'contrat:id,date_debut,date_fin,statut,prix_total',
            ])
            ->withSum('paiements as total_paye_raw', 'montant')
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json($this->paginatedResponse(
            $factures->through(fn(Facture $facture) => $this->formatFacture($facture))
        ));
    }

    public function archive(int $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, 'domiciliataire', 'Accès interdit : les factures appartiennent aux domiciliataires.')) {
            return $blocked;
        }

        $facture = Facture::where('domiciliataire_id', $user->id)->findOrFail($id);
        $facture->forceFill(['archived_at' => now()])->save();

        return response()->json([
            'success' => true,
            'message' => 'Facture archivée.',
            'data' => $this->formatFacture($facture->fresh(['entreprise', 'contrat', 'paiements'])),
        ]);
    }

    public function restore(int $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, 'domiciliataire', 'Accès interdit : les factures appartiennent aux domiciliataires.')) {
            return $blocked;
        }

        $facture = Facture::where('domiciliataire_id', $user->id)->findOrFail($id);
        $facture->forceFill(['archived_at' => null])->save();

        return response()->json([
            'success' => true,
            'message' => 'Facture restaurée.',
            'data' => $this->formatFacture($facture->fresh(['entreprise', 'contrat', 'paiements'])),
        ]);
    }

    public function destroy(int $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessRole($user, 'domiciliataire', 'Accès interdit : les factures appartiennent aux domiciliataires.')) {
            return $blocked;
        }

        $facture = Facture::where('domiciliataire_id', $user->id)->findOrFail($id);

        DB::transaction(function () use ($facture) {
            $facture->paiements()->delete();
            $facture->delete();
        });

        return response()->json(['success' => true, 'message' => 'Facture supprimée.']);
    }

    public function pdf(Request $request, int $id)
    {
        $user = $this->queryTokens->userFromRequest($request);
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

        return $request->query('mode', 'preview') === 'download'
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    private function resolveFacture(int $id, User $user): ?Facture
    {
        if ($user->role !== 'domiciliataire') {
            return null;
        }

        return Facture::with(['entreprise', 'contrat', 'domiciliataire', 'paiements'])
            ->where('domiciliataire_id', $user->id)
            ->find($id);
    }

    private function formatFacture(Facture $facture): array
    {
        $total = (float) $facture->montant_total;
        $paid = (float) ($facture->total_paye_raw ?? $facture->paiements->sum('montant'));
        $remaining = max($total - $paid, 0);
        $isOverdue = $remaining > 0
            && $facture->date_echeance
            && $facture->date_echeance->isPast()
            && !$facture->archived_at;

        return array_merge($facture->toArray(), [
            'total_paye' => number_format($paid, 2, '.', ''),
            'montant_restant' => number_format($remaining, 2, '.', ''),
            'is_overdue' => $isOverdue,
            'effective_statut' => $this->effectiveStatus($facture, $paid, $remaining, $isOverdue),
        ]);
    }

    private function effectiveStatus(Facture $facture, float $paid, float $remaining, bool $isOverdue): string
    {
        return match (true) {
            (bool) $facture->archived_at => 'archived',
            $facture->statut === 'cancelled' => 'cancelled',
            $remaining <= 0 => 'paid',
            $paid > 0 => 'partial',
            $isOverdue => 'overdue',
            default => 'unpaid',
        };
    }
}

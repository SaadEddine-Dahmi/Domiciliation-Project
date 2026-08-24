<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Contrat;
use App\Models\Document;
use App\Models\Entreprise;
use App\Models\Facture;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    // Returns role-appropriate stats: platform-wide for admin,
    // tenant-scoped for domiciliataire, and personal contract/company
    // info for client.
    public function stats()
    {
        $user = auth()->user();
        $role = $user->role;

        if ($role === 'admin') {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_domiciliataires' => $this->safeMetric('admin.total_domiciliataires', fn() => User::where('role', 'domiciliataire')->count(), 0),
                    'total_clients' => $this->safeMetric('admin.total_clients', fn() => Entreprise::count(), 0),
                    'total_contrats' => 0,
                    'contrats_actifs' => 0,
                    'contrats_draft' => 0,
                    'total_documents' => 0,
                    'ca_mensuel' => '0.00',
                    'role' => 'admin',
                ],
            ]);
        }

        if ($role === 'domiciliataire') {
            $tenantId = $user->id;

            $tenantContrats = fn(): Builder => $this->visibleContrats()
                ->where('domiciliataire_id', $tenantId);

            $tenantFactures = fn(): Builder => Facture::query()
                ->where('domiciliataire_id', $tenantId)
                ->when($this->facturesHaveArchivedAt(), fn($q) => $q->whereNull('archived_at'));

            $caMensuel = $this->safeMetric('domiciliataire.ca_mensuel', fn() => $tenantContrats()
                    ->where('statut', 'active')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('prix_total'), 0);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_clients' => $this->safeMetric('domiciliataire.total_clients', fn() => Entreprise::where('domiciliataire_id', $tenantId)->count(), 0),
                    'total_contrats' => $this->safeMetric('domiciliataire.total_contrats', fn() => $tenantContrats()->count(), 0),
                    'contrats_actifs' => $this->safeMetric('domiciliataire.contrats_actifs', fn() => $tenantContrats()->where('statut', 'active')->count(), 0),
                    'contrats_draft' => $this->safeMetric('domiciliataire.contrats_draft', fn() => $tenantContrats()->where('statut', 'draft')->count(), 0),
                    'contrats_expiring_30' => $this->safeMetric('domiciliataire.contrats_expiring_30', fn() => $tenantContrats()
                        ->where('statut', 'active')
                        ->whereBetween('date_fin', [now()->toDateString(), now()->addDays(30)->toDateString()])
                        ->count(), 0),
                    'documents_expiring_30' => $this->safeMetric('domiciliataire.documents_expiring_30', fn() => Document::query()
                        ->whereHas('entreprise', fn($q) => $q->where('domiciliataire_id', $tenantId))
                        ->whereNotNull('date_expiration')
                        ->whereBetween('date_expiration', [now()->toDateString(), now()->addDays(30)->toDateString()])
                        ->count(), 0),
                    'documents_expired' => $this->safeMetric('domiciliataire.documents_expired', fn() => Document::query()
                        ->whereHas('entreprise', fn($q) => $q->where('domiciliataire_id', $tenantId))
                        ->whereNotNull('date_expiration')
                        ->whereDate('date_expiration', '<', now()->toDateString())
                        ->count(), 0),
                    'factures_overdue' => $this->safeMetric('domiciliataire.factures_overdue', fn() => $tenantFactures()
                        ->whereNotIn('statut', ['paid', 'cancelled'])
                        ->whereNotNull('date_echeance')
                        ->whereDate('date_echeance', '<', now()->toDateString())
                        ->count(), 0),
                    'factures_partial' => $this->safeMetric('domiciliataire.factures_partial', fn() => $tenantFactures()
                        ->whereHas('paiements')
                        ->whereNotIn('statut', ['paid', 'cancelled'])
                        ->count(), 0),
                    'total_documents' => $this->safeMetric('domiciliataire.total_documents', fn() => Document::query()
                        ->whereHas('entreprise', fn($q) => $q->where('domiciliataire_id', $tenantId))
                        ->count(), 0),
                    'ca_mensuel' => number_format((float) $caMensuel, 2, '.', ''),
                    'role' => 'domiciliataire',
                ],
            ]);
        }

        $entreprise = $this->safeMetric('client.entreprise', fn() => Entreprise::where('client_user_id', $user->id)
                ->with([
                    'domiciliataire:id,nom,prenom,email,telephone',
                    'contrats' => fn($q) => $this->applyVisibleContratScope($q)->latest()->limit(1),
                ])
                ->first(), null);

        $contrat = $entreprise ? $entreprise->contrats->first() : null;

        return response()->json([
            'success' => true,
            'data' => [
                'entreprise' => $entreprise ? [
                    'id' => $entreprise->id,
                    'raison_sociale' => $entreprise->raison_sociale,
                    'statut' => $entreprise->statut,
                    'ville' => $entreprise->ville,
                ] : null,
                'domiciliataire' => $entreprise?->domiciliataire ? [
                    'nom' => $entreprise->domiciliataire->nom,
                    'prenom' => $entreprise->domiciliataire->prenom,
                    'email' => $entreprise->domiciliataire->email,
                    'telephone' => $entreprise->domiciliataire->telephone,
                ] : null,
                'contrat' => $contrat ? [
                    'id' => $contrat->id,
                    'statut' => $contrat->statut,
                    'date_debut' => $contrat->date_debut?->format('d/m/Y'),
                    'date_fin' => $contrat->date_fin?->format('d/m/Y'),
                    'prix_total' => $contrat->prix_total,
                    'pdf_path' => $contrat->pdf_path,
                    'pdf_url' => $contrat->pdf_path ? asset('storage/' . $contrat->pdf_path) : null,
                    'has_open_renewal' => $contrat?->hasOpenRenewal() ?? false,
                ] : null,
                'timeline' => $entreprise ? $this->clientTimeline($entreprise)->take(8)->values() : [],
                'role' => 'client',
            ],
        ]);
    }

    private function visibleContrats(): Builder
    {
        return $this->applyVisibleContratScope(Contrat::query());
    }

    private function applyVisibleContratScope($query)
    {
        return $this->contratsHaveArchivedAt()
            ? $query->whereNull('archived_at')
            : $query;
    }

    private function contratsHaveArchivedAt(): bool
    {
        try {
            return Schema::hasColumn('contrats', 'archived_at');
        } catch (\Throwable) {
            return false;
        }
    }

    private function facturesHaveArchivedAt(): bool
    {
        try {
            return Schema::hasColumn('factures', 'archived_at');
        } catch (\Throwable) {
            return false;
        }
    }

    private function clientTimeline(Entreprise $entreprise): Collection
    {
        $items = collect();

        $entreprise->loadMissing([
            'contrats:id,entreprise_id,titre_contrat,statut,date_debut,date_fin,created_at',
            'documents:id,entreprise_id,document_type_id,date_expiration,created_at',
            'documents.documentType:id,name',
            'factures:id,entreprise_id,numero_facture,montant_total,statut,date_facture,date_echeance,created_at',
        ]);

        foreach ($entreprise->contrats as $contrat) {
            $items->push([
                'type' => 'contrat',
                'title' => $contrat->titre_contrat ?? "Contrat #{$contrat->id}",
                'description' => "Statut : {$contrat->statut}",
                'date' => $contrat->date_debut?->toDateString() ?? $contrat->created_at?->toDateString(),
                'target' => '/client/contrat',
            ]);
        }

        foreach ($entreprise->documents as $document) {
            $items->push([
                'type' => 'document',
                'title' => $document->documentType?->name ?? 'Document',
                'description' => $document->date_expiration
                    ? 'Expire le ' . $document->date_expiration->format('d/m/Y')
                    : 'Document ajoute',
                'date' => $document->created_at?->toDateString(),
                'target' => '/client/documents',
            ]);
        }

        foreach ($entreprise->factures as $facture) {
            $items->push([
                'type' => 'facture',
                'title' => $facture->numero_facture ?? "Facture #{$facture->id}",
                'description' => number_format((float) $facture->montant_total, 2, '.', ' ') . ' DH',
                'date' => $facture->date_facture?->toDateString() ?? $facture->created_at?->toDateString(),
                'target' => '/client/notifs',
            ]);
        }

        return $items
            ->filter(fn($item) => !empty($item['date']))
            ->sortByDesc('date')
            ->values();
    }

    private function safeMetric(string $key, callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::warning('Dashboard metric failed', [
                'metric' => $key,
                'message' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }
}

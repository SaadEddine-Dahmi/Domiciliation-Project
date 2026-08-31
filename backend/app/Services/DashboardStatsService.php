<?php
// app/Services/DashboardStatsService.php
// Builds role-specific dashboard metrics and client timeline data.

namespace App\Services;

use App\Models\Contrat;
use App\Models\Document;
use App\Models\Entreprise;
use App\Models\Facture;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DashboardStatsService
{
    public function forUser(User $user): array
    {
        return match ($user->role) {
            'admin' => $this->adminStats(),
            'domiciliataire' => $this->domiciliataireStats($user),
            default => $this->clientStats($user),
        };
    }

    private function adminStats(): array
    {
        return [
            'total_domiciliataires' => $this->safeMetric('admin.total_domiciliataires', fn() => User::where('role', 'domiciliataire')->count(), 0),
            'total_clients' => $this->safeMetric('admin.total_clients', fn() => Entreprise::count(), 0),
            'total_contrats' => 0,
            'contrats_actifs' => 0,
            'contrats_draft' => 0,
            'total_documents' => 0,
            'ca_mensuel' => '0.00',
            'role' => 'admin',
        ];
    }

    private function domiciliataireStats(User $user): array
    {
        $tenantId = $user->id;
        $tenantContrats = fn(): Builder => $this->visibleContrats()->where('domiciliataire_id', $tenantId);
        $tenantFactures = fn(): Builder => Facture::query()->where('domiciliataire_id', $tenantId)->whereNull('archived_at');
        $today = now();
        $todayDate = $today->toDateString();
        $nextMonthDate = $today->copy()->addDays(30)->toDateString();

        $caMensuel = $this->safeMetric('domiciliataire.ca_mensuel', fn() => $tenantContrats()
            ->where('statut', 'active')
            ->whereMonth('created_at', $today->month)
            ->whereYear('created_at', $today->year)
            ->sum('prix_total'), 0);

        return [
            'total_clients' => $this->safeMetric('domiciliataire.total_clients', fn() => Entreprise::where('domiciliataire_id', $tenantId)->count(), 0),
            'total_contrats' => $this->safeMetric('domiciliataire.total_contrats', fn() => $tenantContrats()->count(), 0),
            'contrats_actifs' => $this->safeMetric('domiciliataire.contrats_actifs', fn() => $tenantContrats()->where('statut', 'active')->count(), 0),
            'contrats_draft' => $this->safeMetric('domiciliataire.contrats_draft', fn() => $tenantContrats()->where('statut', 'draft')->count(), 0),
            'contrats_expiring_30' => $this->safeMetric('domiciliataire.contrats_expiring_30', fn() => $tenantContrats()
                ->where('statut', 'active')
                ->whereBetween('date_fin', [$todayDate, $nextMonthDate])
                ->count(), 0),
            'documents_expiring_30' => $this->safeMetric('domiciliataire.documents_expiring_30', fn() => $this->tenantDocuments($tenantId)
                ->whereNotNull('date_expiration')
                ->whereBetween('date_expiration', [$todayDate, $nextMonthDate])
                ->count(), 0),
            'documents_expired' => $this->safeMetric('domiciliataire.documents_expired', fn() => $this->tenantDocuments($tenantId)
                ->whereNotNull('date_expiration')
                ->whereDate('date_expiration', '<', $todayDate)
                ->count(), 0),
            'factures_overdue' => $this->safeMetric('domiciliataire.factures_overdue', fn() => $tenantFactures()
                ->whereNotIn('statut', ['paid', 'cancelled'])
                ->whereNotNull('date_echeance')
                ->whereDate('date_echeance', '<', $todayDate)
                ->count(), 0),
            'factures_partial' => $this->safeMetric('domiciliataire.factures_partial', fn() => $tenantFactures()
                ->whereHas('paiements')
                ->whereNotIn('statut', ['paid', 'cancelled'])
                ->count(), 0),
            'total_documents' => $this->safeMetric('domiciliataire.total_documents', fn() => $this->tenantDocuments($tenantId)->count(), 0),
            'ca_mensuel' => number_format((float) $caMensuel, 2, '.', ''),
            'role' => 'domiciliataire',
        ];
    }

    private function clientStats(User $user): array
    {
        $entreprise = $this->safeMetric('client.entreprise', fn() => Entreprise::where('client_user_id', $user->id)
            ->with([
                'domiciliataire:id,nom,prenom,email,telephone',
                'contrats' => fn($query) => $this->visibleContratScope($query)->latest()->limit(1),
            ])
            ->first(), null);

        $contrat = $entreprise ? $entreprise->contrats->first() : null;

        return [
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
        ];
    }

    private function visibleContrats(): Builder
    {
        return $this->visibleContratScope(Contrat::query());
    }

    private function visibleContratScope($query)
    {
        return $query->whereNull('archived_at');
    }

    private function tenantDocuments(int $tenantId): Builder
    {
        return Document::query()
            ->whereHas('entreprise', fn($query) => $query->where('domiciliataire_id', $tenantId));
    }

    private function clientTimeline(Entreprise $entreprise): Collection
    {
        $entreprise->loadMissing([
            'contrats:id,entreprise_id,titre_contrat,statut,date_debut,date_fin,created_at',
            'documents:id,entreprise_id,document_type_id,date_expiration,created_at',
            'documents.documentType:id,name',
            'factures:id,entreprise_id,numero_facture,montant_total,statut,date_facture,created_at',
        ]);

        return collect()
            ->concat($this->contractTimeline($entreprise->contrats))
            ->concat($this->documentTimeline($entreprise->documents))
            ->concat($this->invoiceTimeline($entreprise->factures))
            ->filter(fn($item) => !empty($item['date']))
            ->sortByDesc('date')
            ->values();
    }

    private function contractTimeline(Collection $contrats): Collection
    {
        return $contrats->map(fn($contrat) => [
            'type' => 'contrat',
            'title' => $contrat->titre_contrat ?? "Contrat #{$contrat->id}",
            'description' => "Statut : {$contrat->statut}",
            'date' => $contrat->date_debut?->toDateString() ?? $contrat->created_at?->toDateString(),
            'target' => '/client/contrat',
        ]);
    }

    private function documentTimeline(Collection $documents): Collection
    {
        return $documents->map(fn($document) => [
            'type' => 'document',
            'title' => $document->documentType?->name ?? 'Document',
            'description' => $document->date_expiration
                ? 'Expire le ' . $document->date_expiration->format('d/m/Y')
                : 'Document ajouté',
            'date' => $document->created_at?->toDateString(),
            'target' => '/client/documents',
        ]);
    }

    private function invoiceTimeline(Collection $factures): Collection
    {
        return $factures->map(fn($facture) => [
            'type' => 'facture',
            'title' => $facture->numero_facture ?? "Facture #{$facture->id}",
            'description' => number_format((float) $facture->montant_total, 2, '.', ' ') . ' DH',
            'date' => $facture->date_facture?->toDateString() ?? $facture->created_at?->toDateString(),
            'target' => '/client/notifs',
        ]);
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

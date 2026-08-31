<?php
// app/Http/Controllers/Api/ContratController.php
// Coordinates tenant-scoped contract lifecycle operations and PDF streaming.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\SyncsArticles;
use App\Http\Controllers\Concerns\UsesApiPagination;
use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Entreprise;
use App\Models\User;
use App\Services\Auth\QueryTokenAuthenticator;
use App\Services\Contracts\ContractPdfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContratController extends Controller
{
    use SyncsArticles;
    use UsesApiPagination;

    public function __construct(
        private readonly ContractPdfService $pdfs,
        private readonly QueryTokenAuthenticator $queryTokens,
    ) {
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        try {
            $query = match ($user->role) {
                'admin' => throw new HttpResponseException($this->forbiddenTenantResource()),
                'domiciliataire' => $this->domiciliataireContratsQuery($request, $user),
                'client' => $this->clientContratsQuery($user),
                default => null,
            };

            if (!$query) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $contrats = $query
                ->with($this->contratIndexRelations())
                ->latest()
                ->paginate($this->perPage($request));

            return response()->json($this->paginatedResponse($contrats));
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Throwable $e) {
            Log::error('Contrat index failed', [
                'user_id' => $user?->id,
                'role' => $user?->role,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Erreur lors du chargement des contrats.',
            ], 200);
        }
    }

    public function show(string $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessDomiciliataire($user)) {
            return $blocked;
        }

        $contrat = Contrat::where('domiciliataire_id', $user->id)
            ->with($this->contratDetailRelations())
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $contrat]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessDomiciliataire($user)) {
            return $blocked;
        }

        $data = $this->validatedPayload($request);
        $this->assertEntrepriseBelongsToTenant((int) $data['entreprise_id'], $user);

        $contrat = DB::transaction(function () use ($data, $request, $user) {
            $contrat = Contrat::create([
                'domiciliataire_id' => $user->id,
                'entreprise_id' => $data['entreprise_id'],
                'titre_contrat' => $this->contractTitle($data['titre_contrat'] ?? null),
                'date_debut' => $data['date_debut'],
                'date_fin' => $data['date_fin'] ?? null,
                'duree_mois' => $data['duree_mois'] ?? null,
                'prix_mensuel' => $data['prix_mensuel'] ?? null,
                'prix_total' => $data['prix_total'] ?? null,
                'instruction_no' => $data['instruction_no'] ?? null,
                'ville_signature' => $data['ville_signature'],
                'date_signature' => $data['date_signature'],
                'caution' => $data['caution'] ?? null,
                'mode_paiement' => $data['mode_paiement'] ?? null,
                'statut' => 'draft',
            ]);

            $this->syncArticlesList($contrat, $request->input('articles', []));

            return $contrat;
        });

        return response()->json([
            'success' => true,
            'data' => $contrat->load($this->contractSummaryRelations()),
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessDomiciliataire($user)) {
            return $blocked;
        }

        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);
        if ($blocked = $this->assertEditable($contrat)) {
            return $blocked;
        }

        $data = $this->validatedPayload($request, isUpdate: true);
        if (isset($data['entreprise_id'])) {
            $this->assertEntrepriseBelongsToTenant((int) $data['entreprise_id'], $user);
        }

        DB::transaction(function () use ($contrat, $data, $request) {
            $contrat->update($this->contractUpdatePayload($data));

            if ($request->has('articles')) {
                $this->syncArticlesList($contrat, $request->input('articles', []));
            }
        });

        return response()->json([
            'success' => true,
            'data' => $contrat->load($this->contractSummaryRelations()),
        ]);
    }

    public function activate(Request $request, string $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessDomiciliataire($user)) {
            return $blocked;
        }

        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);
        if ($contrat->statut !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un contrat en brouillon peut être activé.',
            ], 422);
        }

        if (!$contrat->date_signature || !$contrat->ville_signature) {
            return response()->json([
                'success' => false,
                'message' => 'La date et la ville de signature sont obligatoires avant activation.',
            ], 422);
        }

        $request->validate([
            'signed_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        DB::transaction(function () use ($contrat, $request) {
            if ($request->hasFile('signed_pdf')) {
                $path = $request->file('signed_pdf')->storeAs(
                    'contrats/signed/' . $contrat->entreprise_id,
                    'contrat_' . $contrat->id . '_signed.pdf',
                    'public'
                );

                $contrat->update(['scanned_pdf_path' => $path]);
            }

            $contrat->activate();
        });

        return response()->json(['success' => true, 'data' => $contrat->fresh()]);
    }

    public function terminate(string $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessDomiciliataire($user)) {
            return $blocked;
        }

        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);
        if ($contrat->statut !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un contrat actif peut être résilié.',
            ], 422);
        }

        $contrat->terminate();

        return response()->json(['success' => true, 'data' => $contrat->fresh()]);
    }

    public function archive(string $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessDomiciliataire($user)) {
            return $blocked;
        }

        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);
        $contrat->archive();

        return response()->json(['success' => true, 'data' => $contrat->fresh()]);
    }

    public function restore(string $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessDomiciliataire($user)) {
            return $blocked;
        }

        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);
        $contrat->restoreArchive();

        return response()->json(['success' => true, 'data' => $contrat->fresh()]);
    }

    public function destroy(string $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessDomiciliataire($user)) {
            return $blocked;
        }

        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);
        if ($contrat->isLegalised()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce contrat est légalisé et ne peut pas être supprimé.',
            ], 422);
        }

        if ($contrat->statut !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les brouillons peuvent être supprimés. Archivez ce contrat à la place.',
            ], 422);
        }

        DB::transaction(function () use ($contrat) {
            $contrat->articles()->detach();
            $contrat->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Brouillon supprimé.',
        ]);
    }

    public function renew(string $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessDomiciliataire($user)) {
            return $blocked;
        }

        $contrat = Contrat::where('domiciliataire_id', $user->id)
            ->with(['entreprise.representant', 'articles'])
            ->findOrFail($id);

        try {
            $draft = DB::transaction(fn() => $contrat->renew());
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $draft->load($this->contractSummaryRelations()),
        ], 201);
    }

    public function history(string $id)
    {
        $user = auth()->user();
        if ($blocked = $this->denyUnlessDomiciliataire($user)) {
            return $blocked;
        }

        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $contrat->history()->with('changedBy:id,nom,prenom')->limit(100)->get(),
        ]);
    }

    public function streamPdf(Request $request, string $id)
    {
        $user = $this->queryTokens->userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $contrat = $this->resolveContratForStream($id, $user);
        if (!$contrat) {
            return response()->json(['message' => 'Contrat introuvable.'], 404);
        }

        return $this->pdfs->stream($contrat, $request->query('mode', 'preview'));
    }

    private function validatedPayload(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'entreprise_id' => [$required, 'integer', 'exists:entreprises,id'],
            'titre_contrat' => ['nullable', 'string', 'max:255'],
            'date_debut' => [$required, 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'duree_mois' => ['nullable', 'integer', 'min:1'],
            'prix_mensuel' => ['nullable', 'numeric', 'min:0'],
            'prix_total' => ['nullable', 'numeric', 'min:0'],
            'instruction_no' => ['nullable', 'string', 'max:20'],
            'ville_signature' => ['required', 'string', 'max:100'],
            'date_signature' => ['required', 'date'],
            'caution' => ['nullable', 'numeric', 'min:0'],
            'mode_paiement' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', 'in:draft,brouillon,active,expired,terminated'],
            'articles' => ['nullable', 'array'],
            'articles.*.id' => ['required_with:articles', 'string'],
            'articles.*.ordre' => ['nullable', 'integer'],
        ]);
    }

    private function contractUpdatePayload(array $data): array
    {
        $payload = [
            'entreprise_id' => $data['entreprise_id'] ?? null,
            'titre_contrat' => array_key_exists('titre_contrat', $data) ? $this->contractTitle($data['titre_contrat']) : null,
            'date_debut' => $data['date_debut'] ?? null,
            'date_fin' => $data['date_fin'] ?? null,
            'duree_mois' => $data['duree_mois'] ?? null,
            'prix_mensuel' => $data['prix_mensuel'] ?? null,
            'prix_total' => $data['prix_total'] ?? null,
            'instruction_no' => $data['instruction_no'] ?? null,
            'ville_signature' => $data['ville_signature'] ?? null,
            'date_signature' => $data['date_signature'] ?? null,
            'caution' => $data['caution'] ?? null,
            'mode_paiement' => $data['mode_paiement'] ?? null,
        ];

        return array_filter($payload, static fn($value) => $value !== null);
    }

    private function assertEntrepriseBelongsToTenant(int $entrepriseId, User $user): void
    {
        Entreprise::where('id', $entrepriseId)
            ->where('domiciliataire_id', $user->id)
            ->firstOrFail();
    }

    private function assertEditable(Contrat $contrat): ?\Illuminate\Http\JsonResponse
    {
        if (!$contrat->isLegalised()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Ce contrat est légalisé et ne peut plus être modifié.',
        ], 422);
    }

    private function resolveContratForStream(string $id, User $user): ?Contrat
    {
        $query = Contrat::with($this->contractPdfRelations());

        if ($user->role === 'client') {
            $entreprise = Entreprise::where('client_user_id', $user->id)->first(['id']);
            if (!$entreprise) {
                return null;
            }

            $query->where('entreprise_id', $entreprise->id)->whereNull('archived_at');
        } elseif ($user->role === 'domiciliataire') {
            $query->where('domiciliataire_id', $user->id);
        } else {
            return null;
        }

        return $query->find($id);
    }

    private function forbiddenTenantResource(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Accès interdit : les contrats appartiennent aux domiciliataires.',
        ], 403);
    }

    private function denyUnlessDomiciliataire(?User $user): ?\Illuminate\Http\JsonResponse
    {
        return $user?->role === 'domiciliataire'
            ? null
            : $this->forbiddenTenantResource();
    }

    private function domiciliataireContratsQuery(Request $request, User $user): Builder
    {
        $query = Contrat::where('domiciliataire_id', $user->id);
        $this->applyArchiveScope($query, $request);

        if ($request->filled('entreprise_id')) {
            $entrepriseId = (int) $request->query('entreprise_id');
            if (!Entreprise::where('id', $entrepriseId)->where('domiciliataire_id', $user->id)->exists()) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Client introuvable.',
                ], 404));
            }

            $query->where('entreprise_id', $entrepriseId);
        }

        return $query;
    }

    private function clientContratsQuery(User $user): ?Builder
    {
        $entreprise = Entreprise::where('client_user_id', $user->id)->first(['id']);
        if (!$entreprise) {
            return null;
        }

        $query = Contrat::where('entreprise_id', $entreprise->id)->where('statut', 'active');
        $this->applyArchiveScope($query);

        return $query;
    }

    private function applyArchiveScope(Builder $query, ?Request $request = null): Builder
    {
        if (!($request?->boolean('include_archived') ?? false)) {
            $query->whereNull('archived_at');
        }

        return $query;
    }

    private function contratIndexRelations(): array
    {
        return [
            'entreprise.representant',
            'domiciliataire:id,nom,prenom,email,telephone',
            'articles' => fn($query) => $query->orderBy('contrat_articles.ordre'),
            'renewals:id,renewed_from_id,statut,archived_at',
        ];
    }

    private function contratDetailRelations(): array
    {
        return [
            'entreprise.representant',
            'articles' => fn($query) => $query->orderBy('contrat_articles.ordre'),
            'renewedFrom:id,date_debut,date_fin,statut',
            'renewals:id,renewed_from_id,statut,archived_at',
        ];
    }

    private function contractPdfRelations(): array
    {
        return [
            'entreprise.representant',
            'domiciliataire.representant',
            'domiciliataire.profile',
            'articles' => fn($query) => $query->orderBy('contrat_articles.ordre'),
        ];
    }

    private function contractSummaryRelations(): array
    {
        return [
            'entreprise.representant',
            'articles' => fn($query) => $query->orderBy('contrat_articles.ordre'),
        ];
    }

    private function contractTitle(?string $title): string
    {
        return $title ?: 'Contrat de Domiciliation';
    }
}

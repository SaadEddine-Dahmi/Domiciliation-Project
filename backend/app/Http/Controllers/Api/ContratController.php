<?php

// app/Http/Controllers/Api/ContratController.php
//
// Manages the full lifecycle of domiciliation contracts, including renewal.
//
// Routes served:
//   GET    /api/contrats                   → index
//   POST   /api/contrats                   → store  (create draft)
//   GET    /api/contrats/{id}              → show
//   PUT    /api/contrats/{id}              → update
//   POST   /api/contrats/{id}/activate     → activate  (upload signed PDF → active)
//   POST   /api/contrats/{id}/terminate    → terminate
//   POST   /api/contrats/{id}/renew        → renew  (creates a new draft)
//   GET    /api/contrats/{id}/pdf/stream   → streamPdf  (preview AND download —
//                                             the only way a contract PDF is
//                                             produced)
//
// ── PDF strategy (storage-safe) ──────────────────────────────────────────────
//   There is no "generate and save a draft PDF" step. A draft, active,
//   expired or terminated contract is rendered live, on demand, every time
//   someone previews or downloads it — nothing is written to disk for it.
//   This guarantees the PDF always reflects the latest edits.
//
//   The ONE PDF actually persisted to disk is the physically signed document
//   a domiciliataire uploads through activate(). That file becomes the legal
//   source of truth once it exists — stored per client under
//   contrats/signed/{entreprise_id}/ — see activate() below.
//
// ── entreprise.representant eager-load ──────────────────────────────────────
//   The Blade template reads gerant_* fields, which live in the
//   representants table (hasOne Representant on Entreprise), not on
//   entreprises directly.
//
// ── domiciliataire.profile eager-load ─────────────────────────────────────────
//   Company identity printed as the "Domiciliataire" party on the PDF
//   (nom_societe, RC, IF, TP, representant_legal, adresses) lives on
//   domiciliataire_profiles — App\Models\User::profile(), NOT on users
//   directly. Any query that feeds buildTokenMap() must eager-load
//   'domiciliataire.profile', never just 'domiciliataire'.
//
// ── legalised contracts are frozen ───────────────────────────────────────────
//   Once a contract has a scanned_pdf_path (Contrat::isLegalised()), its
//   data fields must not change — the printed/signed paper document is now
//   the legal source of truth, and quietly editing the underlying rows
//   afterward would desync the database from what was actually signed.
//   assertEditable() enforces this as a 422 in update().
//
// ── titre_contrat (dynamic contract title) ───────────────────────────────────
//   Stored exactly as the domiciliataire typed it in wizard step 1 — a
//   free-text field fully chosen by the person creating the contract, and
//   printed as-is as the document title on the PDF. The fallback
//   'Contrat de Domiciliation' is applied ONLY in store()/update() when the
//   field arrives null/empty — never hardcoded into the PDF template.
//
// ── syncArticles() ─────────────────────────────────────────────────────────────
//   Article PKs are integer auto-increment. The frontend sends them as
//   strings (String(a.id)). Accepted formats: [{id:"3",ordre:1}] or flat
//   [3,14]. IDs resolving to <= 0 are silently skipped.
//
// ── streamPdf() ────────────────────────────────────────────────────────────────
//   Registered OUTSIDE auth:sanctum in routes/api.php. A browser
//   <iframe src="…"> cannot attach Authorization: Bearer, so access is
//   validated via a ?token= query param instead.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ContratController extends Controller
{
    // ── Index ──────────────────────────────────────────────────────────────────

    /**
     * GET /api/contrats
     *
     * Returns all contracts belonging to the authenticated domiciliataire,
     * with their related entreprise, ordered articles, and a minimal
     * renewals projection (used by the frontend to detect open renewal
     * drafts without an extra round trip).
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $contrats = Contrat::where('domiciliataire_id', $user->id)
            ->with([
                'entreprise.representant',
                'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
                'renewals:id,renewed_from_id,statut',
            ])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $contrats]);
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    /**
     * GET /api/contrats/{id}
     *
     * Returns one contract with its ordered articles, renewal chain
     * references, and entreprise/representant eagerly loaded.
     * IDOR guard: scoped to the authenticated domiciliataire.
     */
    public function show(string $id)
    {
        $user = auth()->user();

        $contrat = Contrat::where('domiciliataire_id', $user->id)
            ->with([
                'entreprise.representant',
                'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
                'renewedFrom:id,date_debut,date_fin,statut',
                'renewals:id,renewed_from_id,statut',
            ])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $contrat]);
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    /**
     * POST /api/contrats
     *
     * Creates a new draft contract and writes the selected articles with their
     * display order into the contrat_articles pivot table via syncArticles().
     *
     * titre_contrat:
     *   Fully dynamic — whatever the domiciliataire typed in wizard step 1.
     *   Defaults to 'Contrat de Domiciliation' when null or empty.
     *   This is the ONLY place that default is applied on creation.
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'entreprise_id'  => ['required', 'integer', 'exists:entreprises,id'],
            'titre_contrat'  => ['nullable', 'string', 'max:255'],
            'date_debut'     => ['required', 'date'],
            'date_fin'       => ['nullable', 'date', 'after_or_equal:date_debut'],
            'duree_mois'     => ['nullable', 'integer', 'min:1'],
            'prix_mensuel'   => ['nullable', 'numeric', 'min:0'],
            'prix_total'     => ['nullable', 'numeric', 'min:0'],
            'instruction_no' => ['nullable', 'string', 'max:20'],
            'ville_signature'=> ['nullable', 'string', 'max:100'],
            'date_signature' => ['nullable', 'date'],
            'caution'        => ['nullable', 'numeric', 'min:0'],
            'mode_paiement'  => ['nullable', 'string', 'max:100'],
            'statut'         => ['nullable', 'in:draft,active,expired,terminated'],
            'articles'       => ['nullable', 'array'],
            'articles.*.id'  => ['required_with:articles', 'string'],
            'articles.*.ordre' => ['nullable', 'integer'],
        ]);

        $contrat = Contrat::create([
            'domiciliataire_id' => $user->id,
            'entreprise_id'     => $data['entreprise_id'],
            'titre_contrat'     => $data['titre_contrat'] ?: 'Contrat de Domiciliation',
            'date_debut'        => $data['date_debut'],
            'date_fin'          => $data['date_fin']          ?? null,
            'duree_mois'        => $data['duree_mois']        ?? null,
            'prix_mensuel'      => $data['prix_mensuel']      ?? null,
            'prix_total'        => $data['prix_total']        ?? null,
            'instruction_no'    => $data['instruction_no']    ?? null,
            'ville_signature'   => $data['ville_signature']   ?? null,
            'date_signature'    => $data['date_signature']    ?? null,
            'caution'           => $data['caution']           ?? null,
            'mode_paiement'     => $data['mode_paiement']     ?? null,
            'statut'            => $data['statut']            ?? 'draft',
        ]);

        $this->syncArticles($contrat, $request->input('articles', []));

        $contrat->load([
            'entreprise.representant',
            'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
        ]);

        return response()->json(['success' => true, 'data' => $contrat], 201);
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    /**
     * PUT /api/contrats/{id}
     *
     * Updates an existing draft contract and re-syncs article selections.
     * This is also the endpoint the wizard hits when resuming a renewal
     * draft — no special-casing needed, renewal drafts are ordinary
     * Contrat rows in 'draft' status.
     *
     * Blocked once the contract is legalised (scanned_pdf_path set) — see
     * assertEditable(). At that point the signed paper document is the
     * legal source of truth and the DB row must stop changing under it.
     *
     * IDOR guard: scoped to the authenticated domiciliataire.
     */
    public function update(Request $request, string $id)
    {
        $user    = auth()->user();
        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);

        if ($blocked = $this->assertEditable($contrat)) {
            return $blocked;
        }

        $data = $request->validate([
            'entreprise_id'  => ['sometimes', 'integer', 'exists:entreprises,id'],
            'titre_contrat'  => ['nullable', 'string', 'max:255'],
            'date_debut'     => ['sometimes', 'date'],
            'date_fin'       => ['nullable', 'date'],
            'duree_mois'     => ['nullable', 'integer', 'min:1'],
            'prix_mensuel'   => ['nullable', 'numeric', 'min:0'],
            'prix_total'     => ['nullable', 'numeric', 'min:0'],
            'instruction_no' => ['nullable', 'string', 'max:20'],
            'ville_signature'=> ['nullable', 'string', 'max:100'],
            'date_signature' => ['nullable', 'date'],
            'caution'        => ['nullable', 'numeric', 'min:0'],
            'mode_paiement'  => ['nullable', 'string', 'max:100'],
            'statut'         => ['nullable', 'in:draft,active,expired,terminated'],
            'articles'       => ['nullable', 'array'],
            'articles.*.id'  => ['required_with:articles', 'string'],
            'articles.*.ordre' => ['nullable', 'integer'],
        ]);

        $contrat->update(array_filter([
            'entreprise_id'  => $data['entreprise_id']  ?? null,
            'titre_contrat'  => isset($data['titre_contrat'])
                                    ? ($data['titre_contrat'] ?: 'Contrat de Domiciliation')
                                    : null,
            'date_debut'     => $data['date_debut']     ?? null,
            'date_fin'       => $data['date_fin']       ?? null,
            'duree_mois'     => $data['duree_mois']     ?? null,
            'prix_mensuel'   => $data['prix_mensuel']   ?? null,
            'prix_total'     => $data['prix_total']     ?? null,
            'instruction_no' => $data['instruction_no'] ?? null,
            'ville_signature'=> $data['ville_signature']?? null,
            'date_signature' => $data['date_signature'] ?? null,
            'caution'        => $data['caution']        ?? null,
            'mode_paiement'  => $data['mode_paiement']  ?? null,
            'statut'         => $data['statut']         ?? null,
        ], fn($v) => $v !== null));

        if ($request->has('articles')) {
            $this->syncArticles($contrat, $request->input('articles', []));
        }

        $contrat->load([
            'entreprise.representant',
            'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
        ]);

        return response()->json(['success' => true, 'data' => $contrat]);
    }

    // ── Activate ───────────────────────────────────────────────────────────────

    /**
     * POST /api/contrats/{id}/activate
     *
     * Transitions a draft contract to active. Optionally accepts the
     * physically signed PDF the domiciliataire scanned/uploaded — from this
     * point on, Contrat::isLegalised() is true and update() refuses further
     * edits (see assertEditable()).
     *
     * Storage layout (per-client folder):
     *   contrats/signed/{entreprise_id}/contrat_{contrat_id}_signed.pdf
     *
     * The filename is fixed (not a random hash) so re-uploading a corrected
     * scan for the same contract simply replaces the previous file instead
     * of leaving orphaned copies behind.
     */
    public function activate(Request $request, string $id)
    {
        $user = auth()->user();
        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);

        if ($contrat->statut !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un contrat en brouillon peut être activé.',
            ], 422);
        }

        $data = $request->validate([
            'signed_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        if ($request->hasFile('signed_pdf')) {
            $folder   = 'contrats/signed/' . $contrat->entreprise_id;
            $filename = 'contrat_' . $contrat->id . '_signed.pdf';

            $path = $request->file('signed_pdf')->storeAs($folder, $filename, 'public');
            $contrat->update(['scanned_pdf_path' => $path]);
        }

        $contrat->activate();

        return response()->json(['success' => true, 'data' => $contrat->fresh()]);
    }

    // ── Terminate ──────────────────────────────────────────────────────────────

    /**
     * POST /api/contrats/{id}/terminate
     *
     * Transitions an active contract to terminated status.
     */
    public function terminate(string $id)
    {
        $user    = auth()->user();
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

    // ── Renew ──────────────────────────────────────────────────────────────────

    /**
     * POST /api/contrats/{id}/renew
     *
     * Creates a new draft contract carrying over this contract's terms
     * (see Contrat::renew() for the copy rules), linked back via
     * renewed_from_id. The domiciliataire is expected to review/adjust
     * the new draft before activating it — this endpoint never
     * auto-activates.
     *
     * Only callable on 'active' or 'expired' contracts, and only when no
     * open (draft/active) renewal already exists for this contract —
     * both rules enforced in the model, surfaced here as a 422.
     */
    public function renew(string $id)
    {
        $user = auth()->user();
        $contrat = Contrat::where('domiciliataire_id', $user->id)
            ->with(['entreprise.representant', 'articles'])
            ->findOrFail($id);

        try {
            $draft = $contrat->renew();
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $draft->load([
            'entreprise.representant',
            'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
        ]);

        return response()->json(['success' => true, 'data' => $draft], 201);
    }

    // ── History ────────────────────────────────────────────────────────────────

    /**
     * GET /api/contrats/{id}/history
     *
     * Returns the full audit trail for this contract, newest first, with
     * the user who made each change eager-loaded. Mirrors the same pattern
     * already used for clients/representants (see ClientController::history()).
     */
    public function history(string $id)
    {
        $contrat = Contrat::where('domiciliataire_id', auth()->id())->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $contrat->history()->with('changedBy:id,nom,prenom')->get(),
        ]);
    }

    // ── Stream PDF (preview AND download — the only PDF-producing route) ──────

    /**
     * GET /api/contrats/{id}/pdf/stream
     *
     * Streams the contract document for either <iframe> preview or a real
     * file download, depending on ?mode=preview|download.
     *
     * Resolution order:
     *   1. If the contract has been legalized (scanned_pdf_path set — the
     *      real signed document uploaded in activate()), that exact file
     *      is streamed as-is. It is the legal source of truth and must
     *      never be silently replaced by a re-render.
     *   2. Otherwise, the contract is rendered live from current DB state
     *      on every request. Nothing is written to disk on this path.
     *
     * WHY THIS ROUTE IS OUTSIDE auth:sanctum:
     *   A browser <iframe src="…"> issues a plain GET with no custom
     *   headers, so Authorization: Bearer cannot be attached. Access is
     *   instead validated via ?token=.
     */
    public function streamPdf(Request $request, string $id)
    {
        $contrat = Contrat::with([
            'entreprise.representant',
            // FIX: must eager-load the company profile relation, not just
            // 'domiciliataire' — nom_societe/rc/if_fiscal/tp/representant_legal/
            // adresses all live on domiciliataire_profiles via User::profile().
            'domiciliataire.profile',
            'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
        ])->findOrFail($id);

        $mode = $request->query('mode', 'preview'); // preview | download

        $headers = [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => ($mode === 'download' ? 'attachment' : 'inline')
                                      . '; filename="contrat_' . $contrat->id . '.pdf"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'X-Frame-Options'     => 'SAMEORIGIN',
        ];

        // 1. Legalized contract → stream the real signed document, untouched.
        if ($contrat->scanned_pdf_path && Storage::disk('public')->exists($contrat->scanned_pdf_path)) {
            return response(Storage::disk('public')->get($contrat->scanned_pdf_path), 200, $headers);
        }

        // 2. Not legalized yet → render on the fly. Nothing is persisted.
        $tokenMap         = $this->buildTokenMap($contrat);
        $resolvedArticles = $contrat->articles->map(function ($article) use ($tokenMap) {
            $clone       = clone $article;
            $clone->body = $this->resolveTokens($article->body ?? '', $tokenMap);
            return $clone;
        });

        $pdf = Pdf::loadView('pdf.contrat', [
            'contrat'  => $contrat,
            'tokens'   => $tokenMap,
            'articles' => $resolvedArticles,
        ])->setPaper('a4', 'portrait');

        return response($pdf->output(), 200, $headers);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Blocks edits to a legalised contract. Returns a 422 JsonResponse if
     * the contract already has a signed PDF on file, or null when the
     * caller is free to proceed.
     *
     * Called from update() — Contrat::isLegalised() is the single source
     * of truth for "has this contract's paperwork already been signed".
     */
    private function assertEditable(Contrat $contrat): ?\Illuminate\Http\JsonResponse
    {
        if ($contrat->isLegalised()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce contrat est légalisé (PDF signé importé) et ne peut plus être modifié.',
            ], 422);
        }

        return null;
    }

    /**
     * Build the complete {{variable}} → resolved value map from a loaded Contrat.
     * This is the SINGLE SOURCE OF TRUTH for every value the PDF Blade prints —
     * if the Blade references a new $tokens['xxx'] key, add it here first.
     *
     * Requires: entreprise.representant + domiciliataire.profile eager-loaded
     * on $contrat (see streamPdf() above).
     *
     * FALLBACK BEHAVIOUR:
     *   domiciliataire_nom / domiciliataire_representant fall back to the
     *   domiciliataire's personal account name (users.nom + prenom — always
     *   set at registration) when the company profile hasn't been completed
     *   yet, so contracts don't render an entirely blank identity line.
     *   RC / IF / TP / siège-succursales have NO safe fallback and stay
     *   blank until the domiciliataire enters real values in /admin/profile.
     *
     * @return array<string, string>
     */
    private function buildTokenMap(Contrat $contrat): array
    {
        $entreprise     = $contrat->entreprise;
        $representant   = $entreprise?->representant;
        $domiciliataire = $contrat->domiciliataire;
        $profile        = $domiciliataire?->profile;

        $fmt = fn($v) => $v !== null
            ? number_format((float) $v, 2, ',', ' ') . ' DH'
            : '';

        $date = fn($v) => $v
            ? \Carbon\Carbon::parse($v)->format('d/m/Y')
            : '';

        // Domiciliataire's personal account name — always present (set at
        // registration), used as a fallback when the company profile row
        // doesn't exist yet or nom_societe/representant_legal are empty.
        $domiciliataireAccountName = trim(
            ($domiciliataire?->nom    ?? '') . ' ' .
            ($domiciliataire?->prenom ?? '')
        );

        // Build the "Siège N° ... – Succursale1 : ... ; Succursale 2 : ..."
        // inline block from domiciliataire_profiles.adresses. Convention
        // (see DomiciliataireProfileController): index 0 = siège social,
        // every following entry = a succursale. No fallback — legally
        // meaningless without real profile data.
        $adresses = $profile?->adresses_list ?? [];
        $siegeSuccursales = collect($adresses)
            ->map(function ($a) {
                $label = trim($a['label'] ?? '');
                $value = trim($a['value'] ?? '');
                if ($label === '' && $value === '') return null;
                return $label !== '' ? "{$label} : {$value}" : $value;
            })
            ->filter()
            ->implode(' – ');

        return [
            // ── Domiciliataire (service provider) company identity ─────────
            'domiciliataire_nom'          => $profile?->nom_societe
                                                ?: $domiciliataireAccountName,
            'domiciliataire_rc'           => $profile?->rc                    ?? '',
            'domiciliataire_if'           => $profile?->if_fiscal             ?? '',
            'domiciliataire_tp'           => $profile?->tp                    ?? '',

            // Full siège + succursales sentence assembled from adresses.
            'domiciliataire_siege_succursales' => $siegeSuccursales,

            // ── Domiciliataire's legal representative ───────────────────────
            'domiciliataire_representant'           => $profile?->representant_legal
                                                            ?: $domiciliataireAccountName,
            'domiciliataire_identite_representant'  => $profile?->identite_representant ?? '',
            // Prefer the representative's own dedicated contact fields
            // (representant_email/representant_telephone on the profile);
            // fall back to the account owner's login email/telephone if the
            // representative-specific fields haven't been filled in.
            'domiciliataire_representant_telephone' => $profile?->representant_telephone
                                                            ?: ($domiciliataire?->telephone ?? ''),
            'domiciliataire_representant_email'     => $profile?->representant_email
                                                            ?: ($domiciliataire?->email ?? ''),

            // ── Client / domicilié ────────────────────────────────────────────
            'raison_sociale'              => $entreprise?->raison_sociale           ?? '',
            'societe'                     => $entreprise?->raison_sociale           ?? '',
            'forme_juridique'             => $entreprise?->forme_juridique          ?? '',
            'adresse_domiciliation'       => $entreprise?->adresse                  ?? '',
            'ville_client'                => $entreprise?->ville                    ?? '',

            // ── Legal representative (gérant) of the client company ─────────
            'gerant_nom'                  => trim(
                                                ($representant?->nom    ?? '') . ' ' .
                                                ($representant?->prenom ?? '')
                                            ),
            'gerant_prenom'               => $representant?->prenom                 ?? '',
            'gerant_cin'                  => $representant?->cin                    ?? '',
            'gerant_identite'             => $representant?->cin                    ?? '',
            'gerant_telephone'            => $representant?->telephone              ?? '',
            'telephone'                   => $representant?->telephone              ?? '',
            'gerant_email'                => $representant?->email                  ?? '',
            'email'                       => $representant?->email                  ?? '',
            'gerant_adresse'              => $representant?->adresse                ?? '',
            'gerant_nationalite'          => $representant?->nationalite            ?? '',
            'date_naissance'              => $date($representant?->date_naissance),
            'gerant_naissance'            => $date($representant?->date_naissance),

            // ── Contract dates and duration ────────────────────────────────────
            'date_debut'                  => $date($contrat->date_debut),
            'date_fin'                    => $date($contrat->date_fin),
            'date_signature'              => $date($contrat->date_signature),
            'duree_mois'                  => (string) ($contrat->duree_mois         ?? ''),
            'instruction_no'              => $contrat->instruction_no               ?? '',
            'ville_signature'             => $contrat->ville_signature              ?? '',

            // ── Financial ─────────────────────────────────────────────────────
            'prix_mensuel'                => $fmt($contrat->prix_mensuel),
            'prix_total'                  => $fmt($contrat->prix_total),
            'caution'                     => $fmt($contrat->caution),
            'mode_paiement'               => $contrat->mode_paiement                ?? '',

            'redevance_mensuelle'         => $fmt($contrat->prix_mensuel),
            'redevance_annuelle'          => $fmt($contrat->prix_total),

            // Contract title, printed exactly as chosen by the domiciliataire —
            // fully dynamic, never hardcoded (see titre_contrat notes at the
            // top of this file).
            'titre_contrat'               => $contrat->titre_contrat               ?? '',
        ];
    }

    /**
     * Replace every {{key}} token in $text with its resolved value.
     * Tokens are matched case-insensitively; unrecognised tokens are left
     * as-is so the domiciliataire can see which tokens they mistyped.
     */
    private function resolveTokens(string $text, array $tokenMap): string
    {
        foreach ($tokenMap as $key => $value) {
            $text = preg_replace(
                '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/i',
                $value,
                $text
            );
        }
        return $text;
    }

    /**
     * Sync the contrat_articles pivot table with the provided article list.
     * Accepted input formats: [{id:"3",ordre:1}] or flat [3,14].
     * IDs resolving to <= 0 are skipped silently.
     */
    private function syncArticles(Contrat $contrat, array $rawArticles): void
    {
        if (empty($rawArticles)) {
            $contrat->articles()->sync([]);
            return;
        }

        $syncData = [];

        foreach ($rawArticles as $index => $item) {
            if (is_array($item)) {
                $articleId = (int) ($item['id']    ?? 0);
                $ordre     = (int) ($item['ordre'] ?? ($index + 1));
            } else {
                $articleId = (int) $item;
                $ordre     = $index + 1;
            }

            if ($articleId > 0) {
                $syncData[$articleId] = ['ordre' => $ordre];
            }
        }

        $contrat->articles()->sync($syncData);
    }
}

<?php
// ============================================================
// app/Http/Controllers/Api/ContratController.php
//
// Full lifecycle management for domiciliation contracts.
//
// ROUTES SERVED:
//   GET    /api/contrats                 → index        (list all contracts)
//   POST   /api/contrats                 → store        (create draft)
//   GET    /api/contrats/{id}            → show         (single contract)
//   PUT    /api/contrats/{id}            → update       (edit draft)
//   POST   /api/contrats/{id}/activate   → activate     (draft → active)
//   POST   /api/contrats/{id}/terminate  → terminate    (active → terminated)
//   POST   /api/contrats/{id}/renew      → renew        (clone as new draft)
//   POST   /api/contrats/{id}/pdf        → generatePdf  (render + save to disk)
//   GET    /api/contrats/{id}/pdf/stream → streamPdf    (inline iframe preview)
//
// ── ARCHITECTURE NOTES ────────────────────────────────────────────────────────
//
// WHY prepareEntrepriseForPdf() EXISTS:
//   The Blade template reads $contrat->entreprise->gerant_nom, ->gerant_cin,
//   ->tel, ->email, ->adresse, ->date_naissance, ->nationalite, ->nom_societe.
//   NONE of these columns exist on the entreprises table. The data lives in the
//   representants table (one-to-one with entreprises via hasOne Representant).
//   Instead of rewriting the Blade to traverse nested relations everywhere, we
//   inject these as virtual attributes onto the in-memory Eloquent instance using
//   setAttribute(). This is safe — it only changes the PHP object in memory, not
//   the database row.
//
// WHY buildTokenMap() EXISTS:
//   Article bodies can contain {{variable}} placeholders that the domiciliataire
//   types in the article editor. Examples: {{gerant_nom}}, {{date_debut}},
//   {{prix_mensuel}}. resolveTokens() replaces every {{key}} with the real value
//   BEFORE the Blade renders the template. Adding a new token requires only a
//   single entry in buildTokenMap() — no other file needs to change.
//
// WHY streamPdf() IS OUTSIDE auth:sanctum:
//   A browser <iframe src="URL"> sends a plain HTTP GET with no custom headers.
//   There is no JavaScript API to attach Authorization: Bearer to an iframe src.
//   If this route were inside auth:sanctum, every preview attempt would return
//   401 and the iframe would render blank. The route is registered BEFORE the
//   middleware group in routes/api.php.
//
// WHY domiciliataire_adresse READS FROM adresses_list:
//   The users table has NO 'adresse' column. Addresses are stored as a JSON
//   array in the 'adresses' column (cast to array in User model). Each entry is
//   {label: string, value: string}. adresses_list is a User accessor that safely
//   returns this array. The PDF needs the first entry's value as the primary
//   address and all entries for the full footer address chain.
// ============================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ContratController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // PUBLIC ENDPOINTS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/contrats
     *
     * Returns all contracts owned by the authenticated domiciliataire,
     * newest first, with entreprise.representant and ordered articles loaded.
     *
     * entreprise.representant is eager-loaded because the list view shows
     * the client's gérant name (from representants table) in the contract card.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $contrats = Contrat::where('domiciliataire_id', $user->id)
            ->with([
                'entreprise.representant',
                // Always order articles by their display position (set in wizard step 3)
                'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
            ])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $contrats]);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/contrats/{id}
     *
     * Returns a single contract with its ordered articles.
     *
     * IDOR guard: the WHERE domiciliataire_id = auth()->id() scope ensures
     * a user can only fetch their own contracts. Attempting to fetch another
     * tenant's contract returns 404 (indistinguishable from "not found").
     */
    public function show(string $id)
    {
        $user = auth()->user();

        $contrat = Contrat::where('domiciliataire_id', $user->id)
            ->with([
                'entreprise.representant',
                'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
            ])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $contrat]);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/contrats
     *
     * Creates a new draft contract from wizard step 3 data and writes the
     * selected articles (with their display order) into the contrat_articles
     * pivot table via syncArticles().
     *
     * titre_contrat:
     *   Stored exactly as the domiciliataire typed in wizard step 1.
     *   The fallback 'Contrat de Domiciliation' is applied HERE and only here
     *   when the field arrives null or empty. No frontend default is applied.
     *
     * articles:
     *   Expected as [{id: "3", ordre: 1}, {id: "14", ordre: 2}].
     *   syncArticles() handles both object form and flat [3, 14] form.
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'entreprise_id'    => ['required', 'integer', 'exists:entreprises,id'],
            'titre_contrat'    => ['nullable', 'string', 'max:255'],
            'date_debut'       => ['required', 'date'],
            'date_fin'         => ['nullable', 'date', 'after_or_equal:date_debut'],
            'duree_mois'       => ['nullable', 'integer', 'min:1'],
            'prix_mensuel'     => ['nullable', 'numeric', 'min:0'],
            'prix_total'       => ['nullable', 'numeric', 'min:0'],
            'instruction_no'   => ['nullable', 'string', 'max:20'],
            'ville_signature'  => ['nullable', 'string', 'max:100'],
            'date_signature'   => ['nullable', 'date'],
            'caution'          => ['nullable', 'numeric', 'min:0'],
            'mode_paiement'    => ['nullable', 'string', 'max:100'],
            'statut'           => ['nullable', 'in:draft,active,expired,terminated'],
            'articles'         => ['nullable', 'array'],
            'articles.*.id'    => ['required_with:articles', 'string'],
            'articles.*.ordre' => ['nullable', 'integer'],
        ]);

        $contrat = Contrat::create([
            'domiciliataire_id' => $user->id,
            'entreprise_id'     => $data['entreprise_id'],
            // Apply the default title ONLY when the field is null or empty string.
            'titre_contrat'     => $data['titre_contrat'] ?: 'Contrat de Domiciliation',
            'date_debut'        => $data['date_debut'],
            'date_fin'          => $data['date_fin']         ?? null,
            'duree_mois'        => $data['duree_mois']       ?? null,
            'prix_mensuel'      => $data['prix_mensuel']     ?? null,
            'prix_total'        => $data['prix_total']       ?? null,
            'instruction_no'    => $data['instruction_no']   ?? null,
            'ville_signature'   => $data['ville_signature']  ?? null,
            'date_signature'    => $data['date_signature']   ?? null,
            'caution'           => $data['caution']          ?? null,
            'mode_paiement'     => $data['mode_paiement']    ?? null,
            'statut'            => $data['statut']           ?? 'draft',
        ]);

        // Write the article selection and ordering into the pivot table.
        $this->syncArticles($contrat, $request->input('articles', []));

        // Re-load with relations so the response is complete.
        $contrat->load([
            'entreprise.representant',
            'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
        ]);

        return response()->json(['success' => true, 'data' => $contrat], 201);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * PUT /api/contrats/{id}
     *
     * Updates an existing draft contract. Fields not sent in the request are
     * left unchanged (array_filter removes nulls so they are not overwritten).
     *
     * If 'articles' key is present in the request (even as an empty array),
     * the pivot table is re-synced. If the key is absent entirely, article
     * selections are left as-is (allows updating only financial fields without
     * touching the article selection).
     */
    public function update(Request $request, string $id)
    {
        $user    = auth()->user();
        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);

        $data = $request->validate([
            'entreprise_id'    => ['sometimes', 'integer', 'exists:entreprises,id'],
            'titre_contrat'    => ['nullable', 'string', 'max:255'],
            'date_debut'       => ['sometimes', 'date'],
            'date_fin'         => ['nullable', 'date'],
            'duree_mois'       => ['nullable', 'integer', 'min:1'],
            'prix_mensuel'     => ['nullable', 'numeric', 'min:0'],
            'prix_total'       => ['nullable', 'numeric', 'min:0'],
            'instruction_no'   => ['nullable', 'string', 'max:20'],
            'ville_signature'  => ['nullable', 'string', 'max:100'],
            'date_signature'   => ['nullable', 'date'],
            'caution'          => ['nullable', 'numeric', 'min:0'],
            'mode_paiement'    => ['nullable', 'string', 'max:100'],
            'statut'           => ['nullable', 'in:draft,active,expired,terminated'],
            'articles'         => ['nullable', 'array'],
            'articles.*.id'    => ['required_with:articles', 'string'],
            'articles.*.ordre' => ['nullable', 'integer'],
        ]);

        // array_filter with fn($v) => $v !== null prevents overwriting fields
        // that were simply not included in this request.
        $contrat->update(array_filter([
            'entreprise_id'  => $data['entreprise_id']  ?? null,
            // Apply default only when the key was explicitly sent in the request.
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

        // Re-sync articles only if the 'articles' key was present in the request.
        if ($request->has('articles')) {
            $this->syncArticles($contrat, $request->input('articles', []));
        }

        $contrat->load([
            'entreprise.representant',
            'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
        ]);

        return response()->json(['success' => true, 'data' => $contrat]);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/contrats/{id}/activate
     *
     * Transitions a draft → active.
     * Calls Contrat::activate() which calculates next_alert_date and creates
     * an alerte record for the cron job to process.
     *
     * Only drafts can be activated — returns 422 for any other status.
     */
    public function activate(string $id)
    {
        $user    = auth()->user();
        $contrat = Contrat::where('domiciliataire_id', $user->id)->findOrFail($id);

        if ($contrat->statut !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un contrat en brouillon peut être activé.',
            ], 422);
        }

        $contrat->activate();

        return response()->json(['success' => true, 'data' => $contrat->fresh()]);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/contrats/{id}/terminate
     *
     * Transitions active → terminated.
     * Called manually by the domiciliataire when a client leaves.
     *
     * Only active contracts can be terminated.
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

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/contrats/{id}/renew
     *
     * Clones an existing contract (active, expired, or terminated) into a new
     * draft, shifting the dates forward by one contract period.
     *
     * Date logic:
     *   new date_debut = old date_fin + 1 day (seamless continuity)
     *   new date_fin   = new date_debut + duree_mois months
     *   Both are null-safe — if old date_fin was null, the user must set
     *   dates manually after renewal in the contract edit view.
     *
     * Articles are re-synced with the same IDs and ordre values as the source.
     * date_signature is reset to null — the renewal must be re-signed.
     * pdf_path is reset to null — a new PDF must be generated.
     */
    public function renew(string $id)
    {
        $user   = auth()->user();
        $source = Contrat::where('domiciliataire_id', $user->id)
            ->with(['articles' => fn($q) => $q->orderBy('contrat_articles.ordre')])
            ->findOrFail($id);

        if (!in_array($source->statut, ['active', 'expired', 'terminated'])) {
            return response()->json([
                'success' => false,
                'message' => 'Seul un contrat actif, expiré ou résilié peut être renouvelé.',
            ], 422);
        }

        // Shift dates forward by one contract period.
        $newDateDebut = $source->date_fin
            ? $source->date_fin->copy()->addDay()
            : null;

        $newDateFin = ($newDateDebut && $source->duree_mois)
            ? $newDateDebut->copy()->addMonths($source->duree_mois)
            : null;

        $renewed = Contrat::create([
            'domiciliataire_id'         => $user->id,
            'entreprise_id'             => $source->entreprise_id,
            'titre_contrat'             => $source->titre_contrat,
            'date_debut'                => $newDateDebut,
            'date_fin'                  => $newDateFin,
            'duree_mois'                => $source->duree_mois,
            'prix_mensuel'              => $source->prix_mensuel,
            'prix_total'                => $source->prix_total,
            'instruction_no'            => $source->instruction_no,
            'ville_signature'           => $source->ville_signature,
            'date_signature'            => null,   // must be re-signed
            'caution'                   => $source->caution,
            'mode_paiement'             => $source->mode_paiement,
            'notification_delay_months' => $source->notification_delay_months,
            'statut'                    => 'draft',
            'pdf_path'                  => null,   // new PDF must be generated
            'scanned_pdf_path'          => null,
        ]);

        // Re-attach the same article clauses with the same display order.
        $syncData = $source->articles->mapWithKeys(fn($article) => [
            $article->id => ['ordre' => $article->pivot->ordre],
        ])->toArray();

        $renewed->articles()->sync($syncData);

        $renewed->load([
            'entreprise.representant',
            'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
        ]);

        return response()->json(['success' => true, 'data' => $renewed], 201);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/contrats/{id}/pdf
     *
     * Renders the Blade template via dompdf and saves the PDF to disk.
     *
     * Execution flow:
     *   1. Load the contract with all needed relations (entreprise.representant,
     *      domiciliataire, ordered articles).
     *   2. prepareEntrepriseForPdf() — inject virtual attributes onto the
     *      entreprise model instance so the Blade can read gerant_nom etc.
     *   3. buildTokenMap() — build the {{variable}} → value lookup table.
     *   4. For each article, clone it and resolve its body tokens BEFORE passing
     *      to Blade. This is necessary because Blade applies e() (HTML escaping)
     *      to the body string — if we resolve tokens inside Blade after escaping,
     *      the resolved values would themselves be HTML-escaped.
     *   5. Render the Blade view with dompdf, save to storage/public/contrats/,
     *      update pdf_path on the record, and return the public URL.
     *
     * Rate-limited by 'throttle:heavy' middleware in routes/api.php because
     * PDF rendering is CPU-intensive.
     */
    public function generatePdf(string $id)
    {
        $user = auth()->user();

        $contrat = Contrat::where('domiciliataire_id', $user->id)
            ->with([
                'entreprise.representant',
                'domiciliataire',
                'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
            ])
            ->findOrFail($id);

        // Step 2: inject virtual attributes onto the entreprise instance.
        $this->prepareEntrepriseForPdf($contrat);

        // Step 3: build the token → value lookup.
        $tokenMap = $this->buildTokenMap($contrat);

        // Step 4: resolve {{tokens}} in article bodies BEFORE Blade renders them.
        $resolvedArticles = $contrat->articles->map(function ($article) use ($tokenMap) {
            $clone       = clone $article;   // shallow clone — doesn't touch the DB
            $clone->body = $this->resolveTokens($article->body ?? '', $tokenMap);
            return $clone;
        });

        // Step 5: render, save to disk, update the record, return URL.
        $pdf = Pdf::loadView('pdf.contrat', [
            'contrat'  => $contrat,
            'articles' => $resolvedArticles,
        ])->setPaper('a4', 'portrait');

        $filename = 'contrats/contrat_' . $contrat->id . '_' . now()->format('Ymd_His') . '.pdf';
        Storage::disk('public')->put($filename, $pdf->output());
        $contrat->update(['pdf_path' => $filename]);

        return response()->json([
            'success' => true,
            'data'    => [
                'url'      => Storage::disk('public')->url($filename),
                'pdf_path' => $filename,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/contrats/{id}/pdf/stream
     *
     * Streams the PDF inline so the wizard step-4 iframe can display it.
     *
     * WHY THIS ROUTE IS OUTSIDE auth:sanctum (registered before the middleware
     * group in routes/api.php):
     *   A browser <iframe src="URL"> sends a plain GET request. There is no
     *   JavaScript API to attach custom headers (Authorization: Bearer) to an
     *   iframe src URL. If this route were protected by Sanctum, every preview
     *   attempt would receive 401 and the iframe would render blank.
     *
     * Fast path: if the PDF was already generated and saved to disk (pdf_path is
     * set and the file exists), serve it directly without re-rendering.
     *
     * Live path: if no saved PDF exists yet (wizard preview before first save),
     * render on-the-fly without writing to disk so the user sees a live preview.
     */
    public function streamPdf(string $id)
    {
        $contrat = Contrat::with([
            'entreprise.representant',
            'domiciliataire',
            'articles' => fn($q) => $q->orderBy('contrat_articles.ordre'),
        ])->findOrFail($id);

        $headers = [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrat_' . $contrat->id . '.pdf"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'X-Frame-Options'     => 'SAMEORIGIN',
        ];

        // Fast path — serve the previously generated file from disk.
        if ($contrat->pdf_path && Storage::disk('public')->exists($contrat->pdf_path)) {
            return response(
                Storage::disk('public')->get($contrat->pdf_path),
                200,
                $headers
            );
        }

        // Live preview path — render on-the-fly.
        $this->prepareEntrepriseForPdf($contrat);
        $tokenMap = $this->buildTokenMap($contrat);

        $resolvedArticles = $contrat->articles->map(function ($article) use ($tokenMap) {
            $clone       = clone $article;
            $clone->body = $this->resolveTokens($article->body ?? '', $tokenMap);
            return $clone;
        });

        $pdf = Pdf::loadView('pdf.contrat', [
            'contrat'  => $contrat,
            'articles' => $resolvedArticles,
        ])->setPaper('a4', 'portrait');

        return response($pdf->output(), 200, $headers);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * prepareEntrepriseForPdf()
     *
     * Injects virtual attributes onto the Entreprise Eloquent model instance
     * so the Blade template can read them as $contrat->entreprise->gerant_nom
     * etc. without needing those columns to exist on the entreprises table.
     *
     * WHY VIRTUAL ATTRIBUTES ARE NEEDED:
     *   All client-representative data (gérant) lives in the representants table
     *   (one-to-one with entreprises). The Blade was originally written to read
     *   properties directly off the entreprise object. Rather than rewriting
     *   every Blade reference to traverse $contrat->entreprise->representant->...,
     *   we "flatten" the representant data onto the entreprise instance in memory.
     *   setAttribute() is the correct Eloquent way to do this — it writes to the
     *   model's attribute array without touching the database.
     *
     * ATTRIBUTES INJECTED (all sourced from the representants table):
     *   nom_societe    → entreprise.raison_sociale (column name mapping)
     *   gerant_prenom  → representant.prenom
     *   gerant_nom     → "prenom nom" (full name, space-separated, trimmed)
     *   gerant_cin     → representant.cin (CIN or passport number)
     *   tel            → representant.telephone
     *   email          → representant.email
     *   adresse        → representant.adresse (personal address, "Demeurant à")
     *   date_naissance → representant.date_naissance formatted as d/m/Y
     *   nationalite    → representant.nationalite
     *
     * @param Contrat $contrat  Must have entreprise.representant eager-loaded.
     */
    private function prepareEntrepriseForPdf(Contrat $contrat): void
    {
        $entreprise   = $contrat->entreprise;
        $representant = $entreprise?->representant;

        // Guard: if somehow no entreprise is linked, skip silently.
        if (!$entreprise) return;

        // nom_societe: the Blade uses this name but the DB column is raison_sociale.
        $entreprise->setAttribute('nom_societe', $entreprise->raison_sociale ?? '');

        // gerant_prenom: first name only — used in the CONTACT article clause.
        $entreprise->setAttribute('gerant_prenom', $representant?->prenom ?? '');

        // gerant_nom: full display name "PRÉNOM NOM" as used in D'AUTRE PART
        // and the signature block. Trimmed to handle missing prenom gracefully.
        $entreprise->setAttribute(
            'gerant_nom',
            $representant
                ? trim(($representant->prenom ?? '') . ' ' . ($representant->nom ?? ''))
                : ''
        );

        // gerant_cin: CIN national or passport number.
        $entreprise->setAttribute('gerant_cin', $representant?->cin ?? '');

        // tel: gérant's telephone number.
        $entreprise->setAttribute('tel', $representant?->telephone ?? '');

        // email: gérant's email address.
        $entreprise->setAttribute('email', $representant?->email ?? '');

        // adresse: gérant's personal residence address ("Demeurant à" line).
        // Falls back to the entreprise's registered address if no representant.
        $entreprise->setAttribute(
            'adresse',
            $representant?->adresse ?? $entreprise->adresse ?? ''
        );

        // date_naissance: formatted as d/m/Y for the "né(e) le" line.
        // The representant.date_naissance column is cast as a Carbon date in the
        // Representant model, so parse() handles both Carbon and string inputs.
        $entreprise->setAttribute(
            'date_naissance',
            $representant?->date_naissance
                ? \Carbon\Carbon::parse($representant->date_naissance)->format('d/m/Y')
                : ''
        );

        // nationalite: gérant's nationality — shown in the D'AUTRE PART section.
        $entreprise->setAttribute('nationalite', $representant?->nationalite ?? '');
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * buildTokenMap()
     *
     * Builds the complete mapping of {{token_name}} → resolved string value
     * that will be substituted into article bodies before PDF generation.
     *
     * HOW TO ADD A NEW TOKEN:
     *   1. Add one entry: 'token_name' => $someValue
     *   2. resolveTokens() will automatically pick it up.
     *   3. Document the token in the article editor help panel.
     *   No other file needs changing.
     *
     * TOKEN GROUPS:
     *
     *   DOMICILIATAIRE — data from the users table (the service provider):
     *     {{domiciliataire_nom}}       — nom_societe
     *     {{domiciliataire_rc}}        — rc (registre du commerce)
     *     {{domiciliataire_if}}        — if_fiscal
     *     {{domiciliataire_tp}}        — tp (taxe professionnelle)
     *     {{domiciliataire_email}}     — email
     *     {{domiciliataire_telephone}} — telephone
     *     {{domiciliataire_cin}}       — identite_representant (CIN du gérant)
     *     {{domiciliataire_adresse}}   — first address from adresses JSON array
     *     {{domiciliataire_adresse_siege}} — alias for above
     *     {{domiciliataire_representant}}  — representant_legal (full name)
     *     {{domiciliataire_toutes_adresses}} — all addresses joined as a string
     *
     *   CLIENT / DOMICILIÉ — data from entreprises table:
     *     {{raison_sociale}}           — company name
     *     {{societe}}                  — alias for raison_sociale
     *     {{forme_juridique}}          — SARL, SA, etc.
     *     {{adresse_domiciliation}}    — registered address (entreprises.adresse)
     *     {{ville_client}}             — city
     *
     *   GÉRANT (représentant légal du client) — data from representants table:
     *     {{gerant_nom}}               — "PRÉNOM NOM" full name
     *     {{gerant_prenom}}            — first name only
     *     {{gerant_cin}}               — CIN or passport number
     *     {{gerant_telephone}}         — telephone
     *     {{telephone}}                — alias for gerant_telephone
     *     {{gerant_email}}             — email
     *     {{email}}                    — alias for gerant_email
     *     {{gerant_adresse}}           — personal address
     *     {{gerant_nationalite}}       — nationality
     *     {{nationalite}}              — alias for gerant_nationalite
     *     {{date_naissance}}           — date of birth formatted d/m/Y
     *
     *   CONTRACT METADATA:
     *     {{date_debut}}               — start date formatted d/m/Y
     *     {{date_fin}}                 — end date formatted d/m/Y
     *     {{date_signature}}           — signature date formatted d/m/Y
     *     {{duree_mois}}               — duration in months as a string
     *     {{instruction_no}}           — reference number
     *     {{ville_signature}}          — city where contract is signed
     *
     *   FINANCIAL:
     *     {{prix_mensuel}}             — monthly fee formatted "1 500,00 DH"
     *     {{prix_total}}               — total fee formatted "18 000,00 DH"
     *     {{caution}}                  — deposit formatted "2 500,00 DH"
     *     {{mode_paiement}}            — payment method string
     *     {{redevance_mensuelle}}      — alias for prix_mensuel
     *     {{redevance_annuelle}}       — alias for prix_total
     *
     * @param  Contrat $contrat  entreprise.representant + domiciliataire must be eager-loaded.
     * @return array<string, string>
     */
    private function buildTokenMap(Contrat $contrat): array
    {
        $entreprise     = $contrat->entreprise;
        $representant   = $entreprise?->representant;
        $domiciliataire = $contrat->domiciliataire;

        // Format a decimal amount as "1 500,00 DH".
        // Returns empty string when the value is null (field not set).
        $fmt = fn($v) => $v !== null
            ? number_format((float) $v, 2, ',', ' ') . ' DH'
            : '';

        // Format a date value (string, Carbon, or null) as dd/mm/yyyy.
        // Returns empty string when the value is null.
        $date = fn($v) => $v
            ? \Carbon\Carbon::parse($v)->format('d/m/Y')
            : '';

        // ── Domiciliataire address resolution ─────────────────────────────────
        // The users table has NO 'adresse' column. Addresses are stored as a JSON
        // array in users.adresses (cast to PHP array via the 'adresses' cast in
        // the User model). adresses_list is a User accessor that safely returns
        // this array as [] when the column is null.
        //
        // Each entry: {label: "Siège social", value: "123 Rue Mohammed V..."}
        // First entry → siège principal (used as the primary address token)
        // All entries → joined with " – " for the full address chain token
        $adressesList = $domiciliataire?->adresses_list ?? [];

        $domAdresseSiege = !empty($adressesList)
            ? ($adressesList[0]['value'] ?? '')
            : '';

        // Build a human-readable string of all branches for article bodies:
        // "Siège social : 123 Rue X – Succursale 1 : 456 Rue Y"
        $domToutesAdresses = implode(' – ', array_map(
            fn($a) => trim(($a['label'] ?? '') . ' : ' . ($a['value'] ?? '')),
            $adressesList
        ));

        return [
            // ── Domiciliataire (service provider) ──────────────────────────────
            'domiciliataire_nom'             => $domiciliataire?->nom_societe          ?? '',
            'domiciliataire_rc'              => $domiciliataire?->rc                   ?? '',
            'domiciliataire_if'              => $domiciliataire?->if_fiscal            ?? '',
            'domiciliataire_tp'              => $domiciliataire?->tp                   ?? '',
            // FIX: reads from adresses_list JSON array — NOT the non-existent ->adresse column.
            'domiciliataire_adresse'         => $domAdresseSiege,
            'domiciliataire_adresse_siege'   => $domAdresseSiege,       // explicit alias
            'domiciliataire_toutes_adresses' => $domToutesAdresses,     // all branches joined
            'domiciliataire_representant'    => $domiciliataire?->representant_legal   ?? '',
            // NEW TOKENS added in this version:
            'domiciliataire_email'           => $domiciliataire?->email                ?? '',
            'domiciliataire_telephone'       => $domiciliataire?->telephone            ?? '',
            'domiciliataire_cin'             => $domiciliataire?->identite_representant ?? '',

            // ── Client / domicilié (entreprises table) ──────────────────────────
            'raison_sociale'                 => $entreprise?->raison_sociale           ?? '',
            'societe'                        => $entreprise?->raison_sociale           ?? '',
            'forme_juridique'                => $entreprise?->forme_juridique          ?? '',
            'adresse_domiciliation'          => $entreprise?->adresse                  ?? '',
            'ville_client'                   => $entreprise?->ville                    ?? '',

            // ── Gérant (representants table) ────────────────────────────────────
            // gerant_nom: "PRÉNOM NOM" format, consistent with D'AUTRE PART Blade.
            'gerant_nom'                     => trim(
                                                   ($representant?->prenom ?? '') . ' ' .
                                                   ($representant?->nom    ?? '')
                                               ),
            'gerant_prenom'                  => $representant?->prenom                 ?? '',
            'gerant_cin'                     => $representant?->cin                    ?? '',
            'gerant_telephone'               => $representant?->telephone              ?? '',
            'telephone'                      => $representant?->telephone              ?? '',
            'gerant_email'                   => $representant?->email                  ?? '',
            'email'                          => $representant?->email                  ?? '',
            'gerant_adresse'                 => $representant?->adresse                ?? '',
            'gerant_nationalite'             => $representant?->nationalite            ?? '',
            'nationalite'                    => $representant?->nationalite            ?? '',
            'date_naissance'                 => $date($representant?->date_naissance),

            // ── Contract metadata ────────────────────────────────────────────────
            'date_debut'                     => $date($contrat->date_debut),
            'date_fin'                       => $date($contrat->date_fin),
            'date_signature'                 => $date($contrat->date_signature),
            'duree_mois'                     => (string) ($contrat->duree_mois         ?? ''),
            'instruction_no'                 => $contrat->instruction_no               ?? '',
            'ville_signature'                => $contrat->ville_signature              ?? '',

            // ── Financial ────────────────────────────────────────────────────────
            'prix_mensuel'                   => $fmt($contrat->prix_mensuel),
            'prix_total'                     => $fmt($contrat->prix_total),
            'caution'                        => $fmt($contrat->caution),
            'mode_paiement'                  => $contrat->mode_paiement                ?? '',

            // ── Aliases (both spellings accepted to avoid typo-caused blanks) ────
            'redevance_mensuelle'            => $fmt($contrat->prix_mensuel),
            'redevance_annuelle'             => $fmt($contrat->prix_total),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * resolveTokens()
     *
     * Replaces every {{token_name}} occurrence in $text with its resolved value
     * from $tokenMap.
     *
     * BEHAVIOUR:
     *   - Case-insensitive matching ({{DATE_DEBUT}} = {{date_debut}}).
     *   - Optional whitespace inside braces: {{ date_debut }} also works.
     *   - Unrecognised tokens are LEFT AS-IS so the domiciliataire can see
     *     which token names they mistyped, rather than having them silently erased.
     *
     * WHY TOKENS ARE RESOLVED BEFORE THE BLADE RUNS:
     *   The Blade uses {!! nl2br(e($article->body)) !!}. The e() function HTML-
     *   escapes the string. If we tried to resolve tokens inside the Blade after
     *   escaping, the token delimiters {{}} would become &#123;&#123; etc. and
     *   the regex would never match. Resolving before Blade guarantees the real
     *   values appear in the final HTML.
     *
     * @param  string               $text      Raw article body from the database.
     * @param  array<string,string> $tokenMap  Output of buildTokenMap().
     * @return string                           Body with all known tokens substituted.
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

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * syncArticles()
     *
     * Writes the selected article clauses and their display order into the
     * contrat_articles pivot table using Eloquent's sync() method.
     *
     * sync() semantics:
     *   - Detaches articles that are no longer selected.
     *   - Attaches newly selected articles.
     *   - Updates the 'ordre' pivot column for articles that remain selected.
     *   This makes it safe to call on every update — it converges to the correct
     *   state regardless of what was previously stored.
     *
     * ACCEPTED INPUT FORMATS (both sent by the wizard depending on the code path):
     *   Object form: [{id: "3", ordre: 1}, {id: "14", ordre: 2}]
     *   Flat form:   [3, 14]  (ordre defaults to array position + 1)
     *
     * ID HANDLING:
     *   Article PKs are integer auto-increment (bigint). The frontend sends them
     *   as strings (String(a.id)) to avoid Number.MAX_SAFE_INTEGER overflow risk.
     *   We cast every ID to (int) here. IDs that resolve to <= 0 are skipped
     *   silently (guards against malformed payloads).
     *
     * @param Contrat $contrat       The contract whose pivot table is being updated.
     * @param array   $rawArticles   Contents of $request->input('articles', []).
     */
    private function syncArticles(Contrat $contrat, array $rawArticles): void
    {
        if (empty($rawArticles)) {
            // Empty array = "remove all articles from this contract".
            $contrat->articles()->sync([]);
            return;
        }

        $syncData = [];

        foreach ($rawArticles as $index => $item) {
            if (is_array($item)) {
                // Object form: {id: "3", ordre: 1}
                $articleId = (int) ($item['id']    ?? 0);
                $ordre     = (int) ($item['ordre'] ?? ($index + 1));
            } else {
                // Flat form: 3 or "3"
                $articleId = (int) $item;
                $ordre     = $index + 1;
            }

            // Skip invalid IDs (malformed payload guard).
            if ($articleId > 0) {
                $syncData[$articleId] = ['ordre' => $ordre];
            }
        }

        $contrat->articles()->sync($syncData);
    }
}

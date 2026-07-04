<?php
// routes/api.php
//
// All API routes for the application.
//
// Route grouping strategy:
//
//   PUBLIC (no Sanctum auth):
//     Routes that a browser opens via <iframe src> or <a href> — the browser
//     cannot attach an Authorization header to these requests.
//     Each controller validates access through an alternative mechanism
//     (query-param token, or accepts public read for non-sensitive previews).
//
//   AUTHENTICATED (auth:sanctum):
//     All other routes. Require a valid Bearer token in the Authorization header.
//
// Why the PDF stream route must be public:
//   A browser <iframe src="URL"> sends a plain GET request.
//   There is no JavaScript API to attach custom headers to an iframe src.
//   If the route were inside auth:sanctum, every preview attempt would receive
//   a 401 redirect, and the iframe would render a blank or error page instead
//   of the contract PDF.

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ContratController;
use App\Http\Controllers\Api\EntrepriseController;
use App\Http\Controllers\Api\RepresentantController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\DomiciliaireProfileController;
use App\Http\Controllers\Api\ActivationController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\FactureController;

// ── Public auth endpoints ──────────────────────────────────────────────────────

Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// ── Public file endpoints (browser-initiated, no Authorization header possible)

// Document download and preview: controllers validate access via ?token= param
Route::get('/documents/{id}/download', [DocumentController::class, 'download'])
    ->name('documents.download');

Route::get('/documents/{id}/preview', [DocumentController::class, 'preview'])
    ->name('documents.preview');

// Contract PDF stream — used by the preview <iframe> in wizard step 4.
// MUST remain outside auth:sanctum for the same reason as document endpoints.
// ContratController::streamPdf() streams the stored file or renders on-the-fly.
Route::get('/contrats/{id}/pdf/stream', [ContratController::class, 'streamPdf'])
    ->name('contrats.pdf.stream');

// Facture PDF — same pattern as document preview
Route::get('/factures/{id}/pdf', [FactureController::class, 'pdf'])
    ->name('factures.pdf');

// ── Authenticated routes ───────────────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // ── Auth ──────────────────────────────────────────────────────────────────
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // ── Dashboard and profile ─────────────────────────────────────────────────
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/profile', [DomiciliaireProfileController::class, 'show']);
    Route::put('/profile', [DomiciliaireProfileController::class, 'update']);

    // ── Admin ─────────────────────────────────────────────────────────────────
    Route::get('/admin/domiciliataires', [AdminController::class, 'domiciliataires']);
    Route::get('/admin/users/pending', [ActivationController::class, 'pending']);
    Route::post('/admin/users/{id}/approve', [ActivationController::class, 'approve']);
    Route::post('/admin/users/{id}/reject', [ActivationController::class, 'reject']);

    // ── Entreprises and nested representant (1-to-1) ──────────────────────────
    Route::apiResource('entreprises', EntrepriseController::class);
    Route::get('entreprises/{entreprise}/representant', [RepresentantController::class, 'show']);
    Route::post('entreprises/{entreprise}/representant', [RepresentantController::class, 'store']);
    Route::put('entreprises/{entreprise}/representant', [RepresentantController::class, 'update']);
    Route::delete('entreprises/{entreprise}/representant', [RepresentantController::class, 'destroy']);

    // ── Contracts ─────────────────────────────────────────────────────────────
    Route::get('/contrats', [ContratController::class, 'index']);
    Route::post('/contrats', [ContratController::class, 'store']);
    Route::get('/contrats/{id}', [ContratController::class, 'show']);
    Route::put('/contrats/{id}', [ContratController::class, 'update']);
    Route::post('/contrats/{id}/activate', [ContratController::class, 'activate']);
    Route::post('/contrats/{id}/terminate', [ContratController::class, 'terminate']);

    // PDF generation — saves to disk (POST). Heavy rate limit protects server resources.
    Route::post('/contrats/{id}/pdf', [ContratController::class, 'generatePdf'])
        ->middleware('throttle:heavy');

    // NOTE: GET /contrats/{id}/pdf/stream is registered ABOVE, outside this group.

    // ── Payments ──────────────────────────────────────────────────────────────
    Route::get('/contrats/{contrat}/paiements', [PaiementController::class, 'index']);
    Route::post('/contrats/{contrat}/paiements', [PaiementController::class, 'store']);
    Route::get('/contrats/{contrat}/paiements/summary', [PaiementController::class, 'summary']);

    // ── Invoices ──────────────────────────────────────────────────────────────
    // Index requires auth. PDF download is public (registered above).
    Route::get('/factures', [FactureController::class, 'index']);

    // ── Articles (clause library) ─────────────────────────────────────────────
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::post('/articles', [ArticleController::class, 'store']);
    Route::put('/articles/{id}', [ArticleController::class, 'update']);
    Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);

    // ── Clients ───────────────────────────────────────────────────────────────
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);
    Route::put('/clients/{id}/password', [ClientController::class, 'updatePassword']);
    Route::post('/clients', [ClientController::class, 'store']);

    // ── Documents ─────────────────────────────────────────────────────────────
    // CRUD requires auth. Download and preview are public (registered above).
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store'])
        ->middleware('throttle:heavy');
    Route::put('/documents/{id}', [DocumentController::class, 'update']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

    // ── Document types ────────────────────────────────────────────────────────
    Route::get('/document-types', [DocumentTypeController::class, 'index']);
    Route::post('/document-types', [DocumentTypeController::class, 'store']);

    // ── Notifications ─────────────────────────────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences']);
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);

    // ── Messages ──────────────────────────────────────────────────────────────
    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'send']);
    Route::post('/messages/{id}/read', [MessageController::class, 'markRead']);
    Route::get('/messages/{id}/receipt', [MessageController::class, 'receipt']);
});

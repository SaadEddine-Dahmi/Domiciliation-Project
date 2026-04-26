<?php
// routes/api.php

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
use App\Http\Controllers\Api\ActivationController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\FactureController;

// ── Public auth ────────────────────────────────────────────
Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login',    [AuthController::class, 'login']);
});

// ── Document file endpoints (token passed as query param) ──
// These are NOT inside auth:sanctum middleware because the browser
// opens them directly (no way to set Authorization header on <a href>).
// Authentication is done manually inside the controller using the
// ?token= query parameter.
Route::get('/documents/{id}/download', [DocumentController::class, 'download'])
    ->name('documents.download');

Route::get('/documents/{id}/preview', [DocumentController::class, 'preview'])
    ->name('documents.preview');

// ── All other authenticated routes ─────────────────────────
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Auth
    Route::get('/auth/me',      [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Admin
    Route::get('/admin/domiciliataires',     [AdminController::class, 'domiciliataires']);
    Route::get('/admin/users/pending',       [ActivationController::class, 'pending']);
    Route::post('/admin/users/{id}/approve', [ActivationController::class, 'approve']);
    Route::post('/admin/users/{id}/reject',  [ActivationController::class, 'reject']);

    // Entreprises
    Route::apiResource('entreprises', EntrepriseController::class);

    // Representant (1-to-1)
    Route::get('entreprises/{entreprise}/representant',    [RepresentantController::class, 'show']);
    Route::post('entreprises/{entreprise}/representant',   [RepresentantController::class, 'store']);
    Route::put('entreprises/{entreprise}/representant',    [RepresentantController::class, 'update']);
    Route::delete('entreprises/{entreprise}/representant', [RepresentantController::class, 'destroy']);

    // Contrats
    Route::get('/contrats',                 [ContratController::class, 'index']);
    Route::post('/contrats',                [ContratController::class, 'store']);
    Route::get('/contrats/{id}',            [ContratController::class, 'show']);
    Route::put('/contrats/{id}',            [ContratController::class, 'update']);
    Route::post('/contrats/{id}/activate',  [ContratController::class, 'activate']);
    Route::post('/contrats/{id}/terminate', [ContratController::class, 'terminate']);

    // PDF — heavy rate limit
    Route::post('/contrats/{id}/pdf', [ContratController::class, 'generatePdf'])
        ->middleware('throttle:heavy');

    // Paiements
    Route::get('/contrats/{contrat}/paiements',         [PaiementController::class, 'index']);
    Route::post('/contrats/{contrat}/paiements',        [PaiementController::class, 'store']);
    Route::get('/contrats/{contrat}/paiements/summary', [PaiementController::class, 'summary']);

    // Factures
    Route::get('/factures', [FactureController::class, 'index']);

    // Articles
    Route::get('/articles',         [ArticleController::class, 'index']);
    Route::post('/articles',        [ArticleController::class, 'store']);
    Route::put('/articles/{id}',    [ArticleController::class, 'update']);
    Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);

    // Clients
    Route::get('/clients',               [ClientController::class, 'index']);
    Route::get('/clients/{id}',          [ClientController::class, 'show']);
    Route::put('/clients/{id}',          [ClientController::class, 'update']);
    Route::put('/clients/{id}/password', [ClientController::class, 'updatePassword']);

    // Documents (CRUD — auth required)
    Route::get('/documents',         [DocumentController::class, 'index']);
    Route::post('/documents',        [DocumentController::class, 'store'])
        ->middleware('throttle:heavy');
    Route::put('/documents/{id}',    [DocumentController::class, 'update']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

    // Document types
    Route::get('/document-types',  [DocumentTypeController::class, 'index']);
    Route::post('/document-types', [DocumentTypeController::class, 'store']);

    // Notifications
    Route::get('/notifications',             [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read',  [NotificationController::class, 'read']);
    Route::post('/notifications/read-all',   [NotificationController::class, 'readAll']);
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences']);
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);

    // Messages
    Route::get('/messages',              [MessageController::class, 'index']);
    Route::post('/messages',             [MessageController::class, 'send']);
    Route::post('/messages/{id}/read',   [MessageController::class, 'markRead']);
    Route::get('/messages/{id}/receipt', [MessageController::class, 'receipt']);
});
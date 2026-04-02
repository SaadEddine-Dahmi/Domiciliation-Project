<?php
// ============================================================
// routes/api.php — Routes complètes
// ============================================================

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ContratController;
use App\Http\Controllers\Api\EntrepriseController;
use App\Http\Controllers\Api\RepresentantController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\FactureController;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Admin
    Route::get('/admin/domiciliataires', [AdminController::class, 'domiciliataires']);

    // Entreprises + Représentants
    Route::apiResource('entreprises', EntrepriseController::class);
    Route::get('entreprises/{entreprise}/representants', [RepresentantController::class, 'index']);
    Route::post('entreprises/{entreprise}/representants', [RepresentantController::class, 'store']);
    Route::put('entreprises/{entreprise}/representants/{id}', [RepresentantController::class, 'update']);
    Route::delete('entreprises/{entreprise}/representants/{id}', [RepresentantController::class, 'destroy']);

    // Contrats + state machine
    Route::get('/contrats', [ContratController::class, 'index']);
    Route::post('/contrats', [ContratController::class, 'store']);
    Route::get('/contrats/{id}', [ContratController::class, 'show']);
    Route::put('/contrats/{id}', [ContratController::class, 'update']);
    Route::post('/contrats/{id}/pdf', [ContratController::class, 'generatePdf']);
    Route::post('/contrats/{id}/activate', [ContratController::class, 'activate']);
    Route::post('/contrats/{id}/terminate', [ContratController::class, 'terminate']);

    // Paiements (par contrat)
    Route::get('/contrats/{contrat}/paiements', [PaiementController::class, 'index']);
    Route::post('/contrats/{contrat}/paiements', [PaiementController::class, 'store']);
    Route::get('/contrats/{contrat}/paiements/summary', [PaiementController::class, 'summary']);

    // Factures (par contrat)
    Route::get('/factures', [FactureController::class, 'index']);


    // Articles
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::post('/articles', [ArticleController::class, 'store']);
    Route::put('/articles/{id}', [ArticleController::class, 'update']);
    Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);

    // Clients
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);
    Route::put('/clients/{id}/password', [ClientController::class, 'updatePassword']);

    // Documents
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::put('/documents/{id}', [DocumentController::class, 'update']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

    // Document types (pour le dropdown scan)
    Route::get('/document-types', [\App\Http\Controllers\Api\DocumentTypeController::class, 'index']);
    Route::post('/document-types', [\App\Http\Controllers\Api\DocumentTypeController::class, 'store']);

    // Notifications système
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences']);
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);

    // Messagerie directe
    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'send']);
    Route::post('/messages/{id}/read', [MessageController::class, 'markRead']);
    Route::get('/messages/{id}/receipt', [MessageController::class, 'receipt']);
});
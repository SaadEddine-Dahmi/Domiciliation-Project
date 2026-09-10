<?php

use App\Http\Controllers\Api\AccountHistoryController;
use App\Http\Controllers\Api\ActivationController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ContratController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\DomiciliataireProfileController;
use App\Http\Controllers\Api\DomiciliataireRepresentantController;
use App\Http\Controllers\Api\EntrepriseController;
use App\Http\Controllers\Api\FactureController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\RepresentantController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest:sanctum', 'throttle:auth'])->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// Browser-driven file/PDF/photo streams authenticate through query tokens.
Route::get('/documents/{id}/download', [DocumentController::class, 'download'])
    ->name('documents.download');
Route::get('/documents/{id}/preview', [DocumentController::class, 'preview'])
    ->name('documents.preview');
Route::get('/contrats/{id}/pdf/stream', [ContratController::class, 'streamPdf'])
    ->name('contrats.pdf.stream');
Route::get('/factures/{id}/pdf', [FactureController::class, 'pdf'])
    ->name('factures.pdf');
Route::get('/users/{id}/photo', [DomiciliataireProfileController::class, 'photo'])
    ->name('users.photo');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::patch('/clients/{id}/reset-password', [ClientController::class, 'resetPassword']);
    Route::put('/account/password', [AuthController::class, 'changePassword']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/search', SearchController::class);
    Route::get('/profile', [DomiciliataireProfileController::class, 'show']);
    Route::put('/profile', [DomiciliataireProfileController::class, 'update']);
    Route::post('/profile/photo', [DomiciliataireProfileController::class, 'uploadPhoto']);
    Route::delete('/profile/photo', [DomiciliataireProfileController::class, 'deletePhoto']);
    Route::get('/profile/representant', [DomiciliataireRepresentantController::class, 'show']);
    Route::put('/profile/representant', [DomiciliataireRepresentantController::class, 'update']);

    Route::get('/admin/domiciliataires', [AdminController::class, 'domiciliataires']);
    Route::get('/admin/users/pending', [ActivationController::class, 'pending']);
    Route::post('/admin/users/{id}/approve', [ActivationController::class, 'approve']);
    Route::post('/admin/users/{id}/reject', [ActivationController::class, 'reject']);

    Route::apiResource('entreprises', EntrepriseController::class);
    Route::get('entreprises/{entreprise}/representant', [RepresentantController::class, 'show']);
    Route::post('entreprises/{entreprise}/representant', [RepresentantController::class, 'store']);
    Route::put('entreprises/{entreprise}/representant', [RepresentantController::class, 'update']);
    Route::delete('entreprises/{entreprise}/representant', [RepresentantController::class, 'destroy']);
    Route::get('entreprises/{entreprise}/representant/history', [RepresentantController::class, 'history']);

    Route::get('/contrats', [ContratController::class, 'index']);
    Route::post('/contrats', [ContratController::class, 'store']);
    Route::get('/contrats/{id}', [ContratController::class, 'show']);
    Route::put('/contrats/{id}', [ContratController::class, 'update']);
    Route::post('/contrats/{id}/activate', [ContratController::class, 'activate']);
    Route::post('/contrats/{id}/terminate', [ContratController::class, 'terminate']);
    Route::post('/contrats/{id}/archive', [ContratController::class, 'archive']);
    Route::post('/contrats/{id}/restore', [ContratController::class, 'restore']);
    Route::post('/contrats/{id}/renew', [ContratController::class, 'renew']);
    Route::delete('/contrats/{id}', [ContratController::class, 'destroy']);

    Route::get('/contrats/{contrat}/paiements', [PaiementController::class, 'index']);
    Route::post('/contrats/{contrat}/paiements', [PaiementController::class, 'store']);
    Route::get('/contrats/{contrat}/paiements/summary', [PaiementController::class, 'summary']);

    Route::get('/factures', [FactureController::class, 'index']);
    Route::post('/factures/{id}/archive', [FactureController::class, 'archive']);
    Route::post('/factures/{id}/restore', [FactureController::class, 'restore']);
    Route::delete('/factures/{id}', [FactureController::class, 'destroy']);

    Route::get('/articles', [ArticleController::class, 'index']);
    Route::post('/articles', [ArticleController::class, 'store']);
    Route::put('/articles/{id}', [ArticleController::class, 'update']);
    Route::delete('/articles/{id}', [ArticleController::class, 'destroy']);

    Route::get('/templates', [TemplateController::class, 'index']);
    Route::post('/templates', [TemplateController::class, 'store']);
    Route::put('/templates/{id}', [TemplateController::class, 'update']);
    Route::delete('/templates/{id}', [TemplateController::class, 'destroy']);

    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{id}', [ClientController::class, 'show']);
    Route::put('/clients/{id}', [ClientController::class, 'update']);
    Route::post('/clients/{id}/regenerate-password', [ClientController::class, 'resetPassword']);
    Route::put('/clients/{id}/password', [ClientController::class, 'updatePassword']);
    Route::patch('/clients/{id}/status', [ClientController::class, 'toggleStatus']);
    Route::get('/clients/{id}/history', [ClientController::class, 'history']);

    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store'])->middleware('throttle:heavy');
    Route::put('/documents/{id}', [DocumentController::class, 'update']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

    Route::get('/document-types', [DocumentTypeController::class, 'index']);
    Route::post('/document-types', [DocumentTypeController::class, 'store']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences']);
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);

    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'send']);
    Route::post('/messages/{id}/read', [MessageController::class, 'markRead']);
    Route::get('/messages/{id}/receipt', [MessageController::class, 'receipt']);

    Route::get('/account/history', [AccountHistoryController::class, 'index']);
    Route::get('/account/history/export', [AccountHistoryController::class, 'export']);
});

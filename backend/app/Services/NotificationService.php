<?php

namespace App\Services;

use App\Mail\ContractLegalizedMail;
use App\Mail\ContractReminderMail;
use App\Mail\DocumentUploadedMail;
use App\Models\AppNotification;
use App\Models\Contrat;
use App\Models\Document;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Central place for every "who gets notified, and how" decision in the
 * app. Every feature that needs to alert a domiciliataire or a client
 * (in-app notification + email) goes through this service instead of
 * duplicating AppNotification::create() / Mail::send() calls across
 * controllers, observers and scheduled commands.
 *
 * NOTE ON MODEL ASSUMPTIONS
 * -------------------------
 * I wasn't given App\Models\Contrat, Document, Entreprise or User, so the
 * relation names below are inferred from how they're already used
 * elsewhere in the code you sent (routes/console.php,
 * ExpireContractsCommand.php, NotificationController.php):
 *
 *   - Contrat::domiciliataire()   -> User (the domiciliataire's account)
 *   - Contrat::entreprise()       -> Entreprise
 *   - Entreprise::representant()  -> Representant (has ->email)
 *   - Entreprise::clientUser()    -> User (the client's own login, if any)
 *   - Document::entreprise()      -> Entreprise
 *   - User::email_alerts_enabled  -> bool column on domiciliataire accounts
 *
 * If any of these differ in your real models, only resolveClientUser()
 * and resolveClientEmail() at the bottom need to change — every other
 * method calls through them.
 */
class NotificationService
{
    // ── Feature: contract legalized (signed PDF uploaded / contract activated) ──

    /**
     * Fired once a contract moves from "draft" to "active" — i.e. the
     * domiciliataire has uploaded the legalized/signed PDF (see
     * ContratObserver). Notifies BOTH the domiciliataire (confirmation)
     * and the client the contract belongs to.
     */
    public function notifyContractLegalized(Contrat $contrat): void
    {
        $contrat->loadMissing(['entreprise.representant', 'entreprise.clientUser', 'domiciliataire']);

        $title = $contrat->titre_contrat ?: "Contrat #{$contrat->id}";
        $entrepriseName = $contrat->entreprise?->raison_sociale ?? 'Client';

        // 1. In-app + email to the domiciliataire.
        if ($contrat->domiciliataire_id) {
            $this->createInApp(
                userId: $contrat->domiciliataire_id,
                contratId: $contrat->id,
                type: 'contract_legalized',
                message: "✅ « {$title} » a été légalisé et activé pour {$entrepriseName}.",
                data: ['contrat_id' => $contrat->id, 'entreprise' => $entrepriseName],
            );
        }

        // 2. In-app (if the client has a login) + email to the client.
        $clientUser = $this->resolveClientUser($contrat->entreprise);
        if ($clientUser) {
            $this->createInApp(
                userId: $clientUser->id,
                contratId: $contrat->id,
                type: 'contract_legalized',
                message: "✅ Votre contrat « {$title} » a été légalisé et est maintenant actif.",
                data: ['contrat_id' => $contrat->id, 'entreprise' => $entrepriseName],
            );
        }

        $clientEmail = $this->resolveClientEmail($contrat->entreprise);
        if ($clientEmail) {
            Mail::to($clientEmail)->queue(new ContractLegalizedMail($contrat, forDomiciliataire: false));
        }
        if ($contrat->domiciliataire?->email) {
            Mail::to($contrat->domiciliataire->email)->queue(new ContractLegalizedMail($contrat, forDomiciliataire: true));
        }
    }

    // ── Feature: document uploaded by the domiciliataire ───────────────────

    /**
     * Fired whenever a new Document row is created (see DocumentObserver)
     * for one of the domiciliataire's clients — notifies that client
     * in-app + by email.
     */
    public function notifyDocumentUploaded(Document $document): void
    {
        try {
            $document->loadMissing(['entreprise.representant', 'entreprise.clientUser', 'documentType']);

            $entreprise = $document->entreprise;
            if (!$entreprise) {
                return;
            }

            $displayName = $document->documentType?->name
                ?? basename($document->file_path);

            $clientUser = $this->resolveClientUser($entreprise);
            if ($clientUser) {
                $this->createInApp(
                    userId: $clientUser->id,
                    contratId: $document->contrat_id ?? null,
                    type: 'document_uploaded',
                    message: "📄 Un nouveau document « {$displayName} » a été ajouté à votre espace.",
                    data: [
                        'entreprise' => $entreprise->raison_sociale ?? null,
                        'document' => $displayName,
                        // NEW — lets the notifications UI link straight to
                        // GET /api/documents/{id}/preview for this exact file.
                        'document_id' => $document->id,
                    ],
                );
            }

            $clientEmail = $this->resolveClientEmail($entreprise);
            if ($clientEmail) {
                Mail::to($clientEmail)->queue(new DocumentUploadedMail($document, $displayName));
            }
        } catch (\Throwable $e) {
            \Log::error('notifyDocumentUploaded failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyDocumentExpiry(Document $document, string $reminderType): void
    {
        $document->loadMissing(['entreprise.clientUser', 'entreprise.domiciliataire', 'documentType']);

        $entreprise = $document->entreprise;
        if (!$entreprise) {
            return;
        }

        $displayName = $document->documentType?->name ?? 'Document';
        $expiryDate = $document->date_expiration?->format('d/m/Y');
        $message = match ($reminderType) {
            'document_expiry_30' => "Le document « {$displayName} » expire dans 30 jours ({$expiryDate}).",
            'document_expiry_7' => "Le document « {$displayName} » expire dans 7 jours ({$expiryDate}).",
            'document_expiry_1' => "Le document « {$displayName} » expire demain ({$expiryDate}).",
            'document_expired' => "Le document « {$displayName} » a expire le {$expiryDate}.",
            default => "Rappel concernant le document « {$displayName} ».",
        };

        $payload = [
            'document_id' => $document->id,
            'entreprise_id' => $entreprise->id,
            'entreprise' => $entreprise->raison_sociale,
            'date_expiration' => $expiryDate,
        ];

        if ($entreprise->domiciliataire_id) {
            $this->createInApp(
                userId: $entreprise->domiciliataire_id,
                contratId: null,
                type: $reminderType,
                message: "{$entreprise->raison_sociale} - {$message}",
                data: $payload,
            );
        }

        $clientUser = $this->resolveClientUser($entreprise);
        if ($clientUser) {
            $this->createInApp(
                userId: $clientUser->id,
                contratId: null,
                type: $reminderType,
                message: $message,
                data: $payload,
            );
        }
    }
    // ── Feature: contract expiry reminders (used by routes/console.php) ────

    /**
     * Sends one reminder for $reminderType ('pre_expiry_30',
     * 'pre_expiry_15', 'pre_expiry_3' or 'post_expiry') — in-app to the
     * domiciliataire, in-app + email to the client. Dedupe against
     * already-sent reminders is the caller's job (routes/console.php
     * checks the `alertes` table before calling this).
     */
    public function notifyContractReminder(Contrat $contrat, string $reminderType): void
    {
        $title = $contrat->titre_contrat ?: "Contrat #{$contrat->id}";
        $entrepriseName = $contrat->entreprise?->raison_sociale ?? 'un client';
        $dateFin = $contrat->date_fin?->format('d/m/Y');

        $domiciliataireMessage = match ($reminderType) {
            'pre_expiry_30' => "⏳ « {$title} » ({$entrepriseName}) expire dans 1 mois (le {$dateFin}).",
            'pre_expiry_15' => "⏳ « {$title} » ({$entrepriseName}) expire dans 15 jours (le {$dateFin}).",
            'pre_expiry_3' => "⏳ « {$title} » ({$entrepriseName}) expire dans 3 jours (le {$dateFin}).",
            'post_expiry' => "❌ « {$title} » ({$entrepriseName}) a expiré le {$dateFin}.",
            default => "Rappel concernant « {$title} » ({$entrepriseName}).",
        };

        $clientMessage = match ($reminderType) {
            'pre_expiry_30' => "⏳ Votre contrat « {$title} » expire dans 1 mois (le {$dateFin}).",
            'pre_expiry_15' => "⏳ Votre contrat « {$title} » expire dans 15 jours (le {$dateFin}).",
            'pre_expiry_3' => "⏳ Votre contrat « {$title} » expire dans 3 jours (le {$dateFin}).",
            'post_expiry' => "❌ Votre contrat « {$title} » a expiré le {$dateFin}.",
            default => "Rappel concernant votre contrat « {$title} ».",
        };

        // Domiciliataire — in-app (unchanged behavior from before).
        $this->createInApp(
            userId: $contrat->domiciliataire_id,
            contratId: $contrat->id,
            type: $reminderType,
            message: $domiciliataireMessage,
            data: ['contrat_id' => $contrat->id, 'entreprise' => $entrepriseName, 'date_expiration' => $dateFin],
        );

        // Client — in-app (new) + email (existing behavior, now routed here).
        $clientUser = $this->resolveClientUser($contrat->entreprise);
        if ($clientUser) {
            $this->createInApp(
                userId: $clientUser->id,
                contratId: $contrat->id,
                type: $reminderType,
                message: $clientMessage,
                data: ['contrat_id' => $contrat->id, 'date_expiration' => $dateFin],
            );
        }

        $this->sendReminderEmailIfEnabled($contrat, $reminderType);
    }

    /**
     * Emails the client's reminder, provided the domiciliataire has
     * email_alerts_enabled and the client has a reachable address.
     * Queued (::queue, not ::send) so a slow/failing mail provider never
     * blocks the scheduler.
     */
    public function sendReminderEmailIfEnabled(Contrat $contrat, string $reminderType): void
    {
        $domiciliataire = $contrat->domiciliataire;
        if (!$domiciliataire || !$domiciliataire->email_alerts_enabled) {
            return;
        }

        $clientEmail = $this->resolveClientEmail($contrat->entreprise);
        if (!$clientEmail) {
            return;
        }

        Mail::to($clientEmail)->queue(new ContractReminderMail($contrat, $reminderType));
    }

    // ── Shared helpers ──────────────────────────────────────────────────

    private function createInApp(
        int $userId,
        ?int $contratId,
        string $type,
        string $message,
        array $data = [],
        ?string $subject = null,
    ): void {
        AppNotification::create([
            'user_id'    => $userId,
            'contrat_id' => $contratId,
            'type'       => $type,
            'subject'    => $subject ?? $this->defaultSubjectFor($type),
            'message'    => $message,
            'data'       => $data,
            'is_read'    => false,
        ]);
    }

    private function defaultSubjectFor(string $type): string
    {
        return match ($type) {
            'contract_legalized' => 'Contrat activé',
            'document_uploaded'  => 'Nouveau document',
            'document_expiry_30'  => 'Document expire dans 30 jours',
            'document_expiry_7'   => 'Document expire dans 7 jours',
            'document_expiry_1'   => 'Document expire demain',
            'document_expired'    => 'Document expire',
            'pre_expiry_30'      => 'Expiration dans 1 mois',
            'pre_expiry_15'      => 'Expiration dans 15 jours',
            'pre_expiry_3'       => 'Expiration dans 3 jours',
            'post_expiry'        => 'Contrat expiré',
            default               => 'Notification',
        };
    }

    /** The client's own login account for this entreprise, if one exists. */
    private function resolveClientUser(?Entreprise $entreprise): ?User
    {
        return $entreprise?->clientUser ?? null;
    }

    /** Best reachable email for the client: their own login, else the representant's. */
    private function resolveClientEmail(?Entreprise $entreprise): ?string
    {
        if (!$entreprise) {
            return null;
        }

        return $entreprise->clientUser?->email
            ?? $entreprise->representant?->email
            ?? null;
    }
}

<?php
// app/Services/NotificationService.php
// Coordinates in-app and queued email notifications.

namespace App\Services;

use App\Mail\ContractLegalizedMail;
use App\Mail\ContractReminderMail;
use App\Mail\DocumentUploadedMail;
use App\Models\AppNotification;
use App\Models\Contrat;
use App\Models\Document;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function notifyContractLegalized(Contrat $contrat): void
    {
        $contrat->loadMissing(['entreprise.representant', 'entreprise.clientUser', 'domiciliataire']);

        $title = $contrat->titre_contrat ?: "Contrat #{$contrat->id}";
        $entrepriseName = $contrat->entreprise?->raison_sociale ?? 'Client';

        if ($contrat->domiciliataire_id) {
            $this->createInApp(
                userId: $contrat->domiciliataire_id,
                contratId: $contrat->id,
                type: 'contract_legalized',
                message: "Le contrat « {$title} » a été légalisé et activé pour {$entrepriseName}.",
                data: ['contrat_id' => $contrat->id, 'entreprise' => $entrepriseName],
            );
        }

        $clientUser = $this->resolveClientUser($contrat->entreprise);
        if ($clientUser) {
            $this->createInApp(
                userId: $clientUser->id,
                contratId: $contrat->id,
                type: 'contract_legalized',
                message: "Votre contrat « {$title} » a été légalisé et est maintenant actif.",
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

    public function notifyDocumentUploaded(Document $document): void
    {
        try {
            $document->loadMissing(['entreprise.representant', 'entreprise.clientUser', 'documentType']);

            $entreprise = $document->entreprise;
            if (!$entreprise) {
                return;
            }

            $displayName = $document->documentType?->name ?? basename($document->file_path);
            $clientUser = $this->resolveClientUser($entreprise);

            if ($clientUser) {
                $this->createInApp(
                    userId: $clientUser->id,
                    contratId: $document->contrat_id ?? null,
                    type: 'document_uploaded',
                    message: "Un nouveau document « {$displayName} » a été ajouté à votre espace.",
                    data: [
                        'entreprise' => $entreprise->raison_sociale ?? null,
                        'document' => $displayName,
                        'document_id' => $document->id,
                    ],
                );
            }

            $clientEmail = $this->resolveClientEmail($entreprise);
            if ($clientEmail) {
                Mail::to($clientEmail)->queue(new DocumentUploadedMail($document, $displayName));
            }
        } catch (\Throwable $exception) {
            Log::error('Document notification failed.', [
                'document_id' => $document->id,
                'error' => $exception->getMessage(),
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
            'document_expired' => "Le document « {$displayName} » a expiré le {$expiryDate}.",
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

    public function notifyContractReminder(Contrat $contrat, string $reminderType): void
    {
        $contrat->loadMissing(['entreprise.representant', 'entreprise.clientUser', 'domiciliataire']);

        $title = $contrat->titre_contrat ?: "Contrat #{$contrat->id}";
        $entrepriseName = $contrat->entreprise?->raison_sociale ?? 'un client';
        $dateFin = $contrat->date_fin?->format('d/m/Y');

        $this->createInApp(
            userId: $contrat->domiciliataire_id,
            contratId: $contrat->id,
            type: $reminderType,
            message: $this->domiciliataireReminderMessage($reminderType, $title, $entrepriseName, $dateFin),
            data: ['contrat_id' => $contrat->id, 'entreprise' => $entrepriseName, 'date_expiration' => $dateFin],
        );

        $clientUser = $this->resolveClientUser($contrat->entreprise);
        if ($clientUser) {
            $this->createInApp(
                userId: $clientUser->id,
                contratId: $contrat->id,
                type: $reminderType,
                message: $this->clientReminderMessage($reminderType, $title, $dateFin),
                data: ['contrat_id' => $contrat->id, 'date_expiration' => $dateFin],
            );
        }

        $this->sendReminderEmailIfEnabled($contrat, $reminderType);
    }

    public function sendReminderEmailIfEnabled(Contrat $contrat, string $reminderType): void
    {
        $contrat->loadMissing(['entreprise.representant', 'entreprise.clientUser', 'domiciliataire']);

        if (!$contrat->domiciliataire?->email_alerts_enabled) {
            return;
        }

        $clientEmail = $this->resolveClientEmail($contrat->entreprise);
        if ($clientEmail) {
            Mail::to($clientEmail)->queue(new ContractReminderMail($contrat, $reminderType));
        }
    }

    private function createInApp(
        int $userId,
        ?int $contratId,
        string $type,
        string $message,
        array $data = [],
        ?string $subject = null,
    ): void {
        AppNotification::create([
            'user_id' => $userId,
            'contrat_id' => $contratId,
            'type' => $type,
            'subject' => $subject ?? $this->defaultSubjectFor($type),
            'message' => $message,
            'data' => $data,
            'is_read' => false,
        ]);
    }

    private function domiciliataireReminderMessage(string $type, string $title, string $entrepriseName, ?string $dateFin): string
    {
        return match ($type) {
            'pre_expiry_30' => "Le contrat « {$title} » ({$entrepriseName}) expire dans 1 mois (le {$dateFin}).",
            'pre_expiry_15' => "Le contrat « {$title} » ({$entrepriseName}) expire dans 15 jours (le {$dateFin}).",
            'pre_expiry_3' => "Le contrat « {$title} » ({$entrepriseName}) expire dans 3 jours (le {$dateFin}).",
            'post_expiry' => "Le contrat « {$title} » ({$entrepriseName}) a expiré le {$dateFin}.",
            default => "Rappel concernant « {$title} » ({$entrepriseName}).",
        };
    }

    private function clientReminderMessage(string $type, string $title, ?string $dateFin): string
    {
        return match ($type) {
            'pre_expiry_30' => "Votre contrat « {$title} » expire dans 1 mois (le {$dateFin}).",
            'pre_expiry_15' => "Votre contrat « {$title} » expire dans 15 jours (le {$dateFin}).",
            'pre_expiry_3' => "Votre contrat « {$title} » expire dans 3 jours (le {$dateFin}).",
            'post_expiry' => "Votre contrat « {$title} » a expiré le {$dateFin}.",
            default => "Rappel concernant votre contrat « {$title} ».",
        };
    }

    private function defaultSubjectFor(string $type): string
    {
        return match ($type) {
            'contract_legalized' => 'Contrat activé',
            'document_uploaded' => 'Nouveau document',
            'document_expiry_30' => 'Document expire dans 30 jours',
            'document_expiry_7' => 'Document expire dans 7 jours',
            'document_expiry_1' => 'Document expire demain',
            'document_expired' => 'Document expiré',
            'pre_expiry_30' => 'Expiration dans 1 mois',
            'pre_expiry_15' => 'Expiration dans 15 jours',
            'pre_expiry_3' => 'Expiration dans 3 jours',
            'post_expiry' => 'Contrat expiré',
            default => 'Notification',
        };
    }

    private function resolveClientUser(?Entreprise $entreprise): ?User
    {
        return $entreprise?->clientUser ?? null;
    }

    private function resolveClientEmail(?Entreprise $entreprise): ?string
    {
        return $entreprise?->clientUser?->email
            ?? $entreprise?->representant?->email
            ?? null;
    }
}

<?php

namespace App\Observers;

use App\Models\Document;
use App\Services\NotificationService;

/**
 * Notifies the client every time a document is uploaded to their space
 * (POST /api/documents from the domiciliataire's dashboard).
 *
 * NotificationService::notifyDocumentUploaded() already no-ops if the
 * document isn't attached to a client entreprise, so this observer
 * doesn't need its own guard clause for internal-only documents.
 */
class DocumentObserver
{
    public function __construct(private NotificationService $notifications) {}

    public function created(Document $document): void
    {
        $this->notifications->notifyDocumentUploaded($document);
    }
}
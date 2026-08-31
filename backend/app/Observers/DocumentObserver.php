<?php
// app/Observers/DocumentObserver.php
// Dispatches document upload notifications.

namespace App\Observers;

use App\Models\Document;
use App\Services\NotificationService;

class DocumentObserver
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function created(Document $document): void
    {
        $this->notifications->notifyDocumentUploaded($document);
    }
}

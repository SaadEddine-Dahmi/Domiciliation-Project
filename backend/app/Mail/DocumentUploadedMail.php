<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentUploadedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public string $displayName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nouveau document disponible : {$this->displayName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-uploaded',
            with: [
                'document' => $this->document,
                'displayName' => $this->displayName,
                'entreprise' => $this->document->entreprise,
            ],
        );
    }
}
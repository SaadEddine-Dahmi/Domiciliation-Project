<?php

namespace App\Mail;

use App\Models\Contrat;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a contract is legalized (signed PDF uploaded, status flips
 * draft -> active). The same mailable serves both recipients —
 * $forDomiciliataire only changes the copy, not the structure.
 */
class ContractLegalizedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contrat $contrat,
        public bool $forDomiciliataire = false,
    ) {
    }

    public function envelope(): Envelope
    {
        $title = $this->contrat->titre_contrat ?: "Contrat #{$this->contrat->id}";

        return new Envelope(
            subject: $this->forDomiciliataire
            ? "« {$title} » a été activé"
            : "Votre contrat « {$title} » est maintenant actif",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contract-legalized',
            with: [
                'contrat' => $this->contrat,
                'entreprise' => $this->contrat->entreprise,
                'domiciliataire' => $this->contrat->domiciliataire,
                'forDomiciliataire' => $this->forDomiciliataire,
            ],
        );
    }
}
<?php
// app/Mail/ContractLegalizedMail.php
// Builds the contract legalization email.

namespace App\Mail;

use App\Models\Contrat;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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

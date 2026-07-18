<?php
// app/Mail/ContractReminderMail.php

namespace App\Mail;

use App\Models\Contrat;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contrat $contrat,
        public string $reminderType, // 'pre_expiry_30' | 'pre_expiry_15' | 'pre_expiry_3' | 'post_expiry'
    ) {}

    public function envelope(): Envelope
    {
        $domiciliataire = $this->contrat->domiciliataire;

        return new Envelope(
            subject: $this->subjectFor($this->reminderType),
            // Client replies land directly in the domiciliataire's own
            // account inbox — the same email they use to log in.
            replyTo: [[
                'address' => $domiciliataire->email,
                'name'    => $domiciliataire->nom_societe ?? $domiciliataire->nom,
            ]],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contract-reminder',
            with: [
                'contrat'        => $this->contrat,
                'entreprise'     => $this->contrat->entreprise,
                'domiciliataire' => $this->contrat->domiciliataire,
                'reminderType'   => $this->reminderType,
            ],
        );
    }

    private function subjectFor(string $type): string
    {
        $societe = $this->contrat->domiciliataire?->nom_societe ?? 'Votre domiciliataire';

        return match ($type) {
            'pre_expiry_30' => "{$societe} — Votre contrat expire dans 1 mois",
            'pre_expiry_15' => "{$societe} — Votre contrat expire dans 15 jours",
            'pre_expiry_3'  => "{$societe} — Votre contrat expire dans 3 jours",
            'post_expiry'   => "{$societe} — Votre contrat a expiré",
            default         => "{$societe} — Rappel concernant votre contrat",
        };
    }
}

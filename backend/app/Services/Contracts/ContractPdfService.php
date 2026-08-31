<?php
// app/Services/Contracts/ContractPdfService.php
// Streams signed or live-rendered contract PDFs without controller I/O logic.

namespace App\Services\Contracts;

use App\Models\Contrat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ContractPdfService
{
    public function __construct(private readonly ContractTokenResolver $tokens)
    {
    }

    public function stream(Contrat $contrat, string $mode = 'preview'): Response
    {
        $headers = $this->headers($contrat, $mode);

        if ($contrat->scanned_pdf_path && Storage::disk('public')->exists($contrat->scanned_pdf_path)) {
            return response(Storage::disk('public')->get($contrat->scanned_pdf_path), 200, $headers);
        }

        $tokenMap = $this->tokens->map($contrat);
        $articles = $contrat->articles->map(function ($article) use ($tokenMap) {
            $resolved = clone $article;
            $resolved->body = $this->tokens->resolve($article->body ?? '', $tokenMap);

            return $resolved;
        });

        $pdf = Pdf::loadView('pdf.contrat', [
            'contrat' => $contrat,
            'tokens' => $tokenMap,
            'articles' => $articles,
        ])->setPaper('a4', 'portrait');

        return response($pdf->output(), 200, $headers);
    }

    private function headers(Contrat $contrat, string $mode): array
    {
        $disposition = $mode === 'download' ? 'attachment' : 'inline';

        return [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="contrat_' . $contrat->id . '.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Frame-Options' => 'SAMEORIGIN',
        ];
    }
}

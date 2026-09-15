<?php
// app/Services/Contracts/ContractStorageService.php
// Handles tenant-scoped storage and temporary access for signed contract PDFs.

namespace App\Services\Contracts;

use App\Models\Contrat;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractStorageService
{
    public function disk(): string
    {
        $disk = config('filesystems.contracts_disk');

        if (!is_string($disk) || $disk === '') {
            throw new \RuntimeException('Contract storage disk is not configured.');
        }

        return $disk;
    }

    public function storeSignedPdf(Contrat $contrat, UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new FileNotFoundException('The uploaded contract PDF is missing or invalid.');
        }

        $tenantId = (int) $contrat->domiciliataire_id;
        $filename = sprintf('contract_%d_signed_%s.pdf', $contrat->id, Str::uuid());
        $path = "tenants/{$tenantId}/contracts/{$filename}";

        try {
            $stored = Storage::disk($this->disk())->putFileAs(
                "tenants/{$tenantId}/contracts",
                $file,
                $filename,
                ['visibility' => 'private', 'ContentType' => 'application/pdf']
            );
        } catch (\Throwable $e) {
            Log::error('Contract PDF upload failed', [
                'contract_id' => $contrat->id,
                'tenant_id' => $tenantId,
                'disk' => $this->disk(),
                'message' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Unable to store the contract PDF.', previous: $e);
        }

        if (!$stored || $stored !== $path) {
            throw new \RuntimeException('Unable to store the contract PDF.');
        }

        return $stored;
    }

    public function exists(Contrat $contrat): bool
    {
        return $contrat->scanned_pdf_path
            ? Storage::disk($this->disk())->exists($contrat->scanned_pdf_path)
            : false;
    }

    public function get(Contrat $contrat): string
    {
        if (!$this->exists($contrat)) {
            throw new FileNotFoundException('The stored contract PDF was not found.');
        }

        try {
            return Storage::disk($this->disk())->get($contrat->scanned_pdf_path);
        } catch (\Throwable $e) {
            Log::error('Contract PDF read failed', [
                'contract_id' => $contrat->id,
                'tenant_id' => $contrat->domiciliataire_id,
                'disk' => $this->disk(),
                'message' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Unable to read the contract PDF.', previous: $e);
        }
    }

    public function temporaryUrl(Contrat $contrat, ?\DateTimeInterface $expiration = null): string
    {
        if (!$this->exists($contrat)) {
            throw new FileNotFoundException('The stored contract PDF was not found.');
        }

        try {
            return Storage::disk($this->disk())->temporaryUrl(
                $contrat->scanned_pdf_path,
                $expiration ?? now()->addMinutes(15),
                [
                    'ResponseContentType' => 'application/pdf',
                    'ResponseContentDisposition' => 'inline; filename="contrat_' . $contrat->id . '.pdf"',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Contract PDF temporary URL generation failed', [
                'contract_id' => $contrat->id,
                'tenant_id' => $contrat->domiciliataire_id,
                'disk' => $this->disk(),
                'message' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Unable to generate a temporary contract PDF URL.', previous: $e);
        }
    }
}

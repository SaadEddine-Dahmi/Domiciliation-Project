<?php
// app/Services/Documents/DocumentStorageService.php
// Encapsulates document storage disk, streaming, and MIME handling.

namespace App\Services\Documents;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentStorageService
{
    public function disk(): string
    {
        $disk = config('filesystems.documents_disk');

        if (!is_string($disk) || $disk === '') {
            throw new \RuntimeException('Document storage disk is not configured.');
        }

        return $disk;
    }

    public function store(UploadedFile $file, int $tenantId, int $entrepriseId): string
    {
        try {
            $path = $file->store("tenants/{$tenantId}/documents/{$entrepriseId}", $this->disk());
        } catch (\Throwable $e) {
            Log::error('Document upload failed', [
                'tenant_id' => $tenantId,
                'entreprise_id' => $entrepriseId,
                'disk' => $this->disk(),
                'message' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Unable to store the uploaded document.', previous: $e);
        }

        if (!$path) {
            throw new \RuntimeException('Unable to store the uploaded document.');
        }

        return $path;
    }

    public function exists(Document $document): bool
    {
        return Storage::disk($this->disk())->exists($document->file_path);
    }

    public function delete(Document $document): void
    {
        if ($this->exists($document)) {
            Storage::disk($this->disk())->delete($document->file_path);
        }
    }

    public function streamDownload(Document $document, string $filename): StreamedResponse
    {
        return response()->streamDownload(fn() => $this->passthrough($document), $filename, [
            'Content-Type' => $this->mimeType($document->file_path),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function streamInline(Document $document): StreamedResponse
    {
        return response()->stream(fn() => $this->passthrough($document), 200, [
            'Content-Type' => $this->mimeType($document->file_path),
            'Content-Length' => Storage::disk($this->disk())->size($document->file_path),
            'Content-Disposition' => 'inline; filename="' . basename($document->file_path) . '"',
            'Cache-Control' => 'no-store, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }

    private function passthrough(Document $document): void
    {
        $stream = Storage::disk($this->disk())->readStream($document->file_path);
        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to read the stored document.');
        }

        fpassthru($stream);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }
}

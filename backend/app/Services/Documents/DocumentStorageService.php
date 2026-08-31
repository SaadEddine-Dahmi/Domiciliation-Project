<?php
// app/Services/Documents/DocumentStorageService.php
// Encapsulates document storage disk, streaming, and MIME handling.

namespace App\Services\Documents;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentStorageService
{
    public function disk(): string
    {
        return array_key_exists('private', config('filesystems.disks', [])) ? 'private' : 'local';
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
        fpassthru($stream);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }
}

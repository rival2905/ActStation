<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Models\EmailMessage;
use finfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentDownloadController extends Controller
{
    public function download(Request $request, int|string $message, int|string $index): BinaryFileResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Link download tidak valid atau telah kedaluwarsa.');
        }

        $emailMessage = EmailMessage::find($message);
        if (! $emailMessage) {
            abort(404, 'Pesan email tidak ditemukan.');
        }

        $payload = $emailMessage->payload ?? [];
        $attachments = $payload['attachments'] ?? [];
        if (! is_array($attachments) || ! is_numeric($index)) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        $idx = (int) $index;
        if ($idx < 0 || $idx >= count($attachments)) {
            abort(404, 'Indeks lampiran tidak valid.');
        }

        $att = array_values($attachments)[$idx];
        $filePath = is_array($att) ? (string) ($att['path'] ?? '') : (string) $att;
        $fileName = is_array($att) ? (string) ($att['name'] ?? basename($filePath)) : basename($filePath);

        $disk = Storage::disk('local');
        $fullPath = (! str_starts_with($filePath, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $filePath))
            ? $disk->path($filePath)
            : $filePath;

        $realPath = realpath($fullPath);
        if (! $realPath || ! file_exists($realPath) || ! is_file($realPath)) {
            abort(404, 'Berkas lampiran fisik tidak ditemukan.');
        }

        $allowedDir = realpath($disk->path('email-attachments'));
        if (! $allowedDir) {
            abort(403, 'Akses direktori berkas ditolak.');
        }
        $allowedPrefix = rtrim($allowedDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        // Whitelist check: Must reside strictly within local disk email-attachments directory
        if (! str_starts_with($realPath, $allowedPrefix)) {
            abort(403, 'Akses direktori berkas ditolak.');
        }

        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fileInfo->file($realPath) ?: (mime_content_type($realPath) ?: 'application/octet-stream');

        return response()->download($realPath, $fileName, [
            'Content-Type' => $mime,
        ]);
    }
}

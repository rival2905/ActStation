<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Contracts\TeamDocumentInspection;
use App\Modules\Community\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamDocumentInspectionService implements TeamDocumentInspection
{
    public function find(string $ownerType, int $ownerId, string $code): ?array
    {
        $doc = $this->query($ownerType, $ownerId, $code)->first();
        if (! $doc) {
            return null;
        }

        return [
            'name' => $doc->name,
            'status' => $doc->status,
            'notes' => $doc->notes,
            'exists' => ! $doc->is_public && $doc->file_path && Storage::disk('local')->exists($doc->file_path),
            'version' => $this->version($doc),
        ];
    }

    public function download(string $ownerType, int $ownerId, string $code): StreamedResponse
    {
        $doc = $this->query($ownerType, $ownerId, $code)->firstOrFail();
        $this->available($doc);

        return Storage::disk('local')->download($doc->file_path, basename($doc->name ?: $code));
    }

    public function review(string $ownerType, int $ownerId, string $code, string $version, string $status, ?string $notes, int $reviewerId): void
    {
        DB::transaction(function () use ($ownerType, $ownerId, $code, $version, $status, $notes, $reviewerId): void {
            $doc = $this->query($ownerType, $ownerId, $code)->lockForUpdate()->firstOrFail();
            $this->available($doc);
            abort_unless(hash_equals($this->version($doc), $version), 409, 'Dokumen berubah. Muat ulang detail sebelum memeriksa.');
            $doc->update(['status' => $status, 'notes' => $notes, 'verified_by' => $reviewerId, 'verified_at' => now()]);
        });
    }

    private function query(string $ownerType, int $ownerId, string $code): Builder
    {
        return Document::query()->where('documentable_type', $ownerType)->where('documentable_id', $ownerId)->where('category', $code)->latest('id');
    }

    private function available(Document $doc): void
    {
        abort_unless(! $doc->is_public && $doc->file_path && Storage::disk('local')->exists($doc->file_path), 404, 'Berkas privat tidak tersedia.');
    }

    private function version(Document $doc): string
    {
        return hash('sha256', json_encode([$doc->id, $doc->file_path, $doc->updated_at?->format('Y-m-d H:i:s.u'), $doc->status, $doc->notes, $doc->verified_by, $doc->verified_at?->format('Y-m-d H:i:s.u')]));
    }
}

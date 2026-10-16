<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Contracts\AthleteDocumentVault;
use App\Models\User;
use App\Modules\Community\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AthleteDocumentVaultService implements AthleteDocumentVault
{
    /**
     * @param  array<int, string>  $codes
     * @return array<string, array{exists: bool, status: ?string}>
     */
    public function summaries(int $userId, array $codes): array
    {
        $results = [];
        foreach ($codes as $code) {
            $results[(string) $code] = [
                'exists' => false,
                'status' => null,
            ];
        }

        if (empty($codes)) {
            return $results;
        }

        $userMorphTypes = array_unique([User::class, (new User)->getMorphClass()]);

        $documents = Document::query()
            ->whereIn('documentable_type', $userMorphTypes)
            ->where('documentable_id', $userId)
            ->whereIn('category', $codes)
            ->orderBy('id', 'asc')
            ->get();

        foreach ($documents as $doc) {
            $cat = (string) $doc->category;
            if (! array_key_exists($cat, $results)) {
                continue;
            }

            $results[$cat] = [
                'exists' => ! empty($doc->file_path) && ! (bool) $doc->is_public && Storage::disk('local')->exists((string) $doc->file_path),
                'status' => $doc->status !== null ? (string) $doc->status : null,
            ];
        }

        return $results;
    }

    public function upload(int $userId, string $code, UploadedFile $file): void
    {
        $storedPath = null;

        try {
            $storedPath = $file->store('athlete_documents', 'local');
            if (! $storedPath) {
                throw new \RuntimeException('Gagal menyimpan berkas dokumen atlet.');
            }

            DB::transaction(function () use ($userId, $code, $file, $storedPath): void {
                $user = User::query()->findOrFail($userId);
                $userMorph = $user->getMorphClass();

                /** @var Document|null $existingDoc */
                $existingDoc = Document::query()
                    ->where('documentable_type', $userMorph)
                    ->where('documentable_id', $userId)
                    ->where('category', $code)
                    ->lockForUpdate()
                    ->first();

                $oldFilePath = $existingDoc?->file_path;
                $docName = $file->getClientOriginalName() ?: $code;

                if ($existingDoc) {
                    $existingDoc->update([
                        'name' => $docName,
                        'file_path' => $storedPath,
                        'status' => 'pending',
                        'is_public' => false,
                        'verified_at' => null,
                        'verified_by' => null,
                        'notes' => null,
                    ]);
                } else {
                    Document::query()->create([
                        'documentable_type' => $userMorph,
                        'documentable_id' => $userId,
                        'category' => $code,
                        'name' => $docName,
                        'file_path' => $storedPath,
                        'status' => 'pending',
                        'is_public' => false,
                        'verified_at' => null,
                        'verified_by' => null,
                        'notes' => null,
                    ]);
                }

                if ($oldFilePath && $oldFilePath !== $storedPath) {
                    DB::afterCommit(function () use ($oldFilePath): void {
                        if (Storage::disk('local')->exists($oldFilePath)) {
                            Storage::disk('local')->delete($oldFilePath);
                        }
                    });
                }
            });
        } catch (Throwable $e) {
            if ($storedPath && Storage::disk('local')->exists($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $e;
        }
    }
}

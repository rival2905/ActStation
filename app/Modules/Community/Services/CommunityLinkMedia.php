<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityLinkPage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunityLinkMedia
{
    public function upload(Community $community, CommunityLinkPage $page, User $actor, UploadedFile $file, int $revision, string $kind = 'avatar'): void
    {
        abort_unless(in_array($kind, ['avatar', 'background'], true), 422);
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) || $info[0] > 2048 || $info[1] > 2048 || $file->getSize() > 2097152) {
            throw ValidationException::withMessages(['avatar' => 'Gambar PNG/JPEG/WebP maksimal 2 MB dan 2048 × 2048 diperlukan.']);
        }
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages(['avatar' => 'Gambar tidak dapat dibaca.']);
        }
        ob_start();
        imagewebp($image, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $path = null;
        try {
            DB::transaction(function () use ($community, $page, $actor, $revision, $bytes, $kind, &$path): void {
                $community = Community::whereKey($community->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
                abort_unless($actor->fresh()?->is_active, 403);
                Gate::forUser($actor)->authorize('update', $community);
                $page = CommunityLinkPage::whereKey($page->id)->where('community_id', $community->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $page->revision === $revision, 409, 'Halaman berubah. Muat ulang.');
                $files = Storage::disk('local')->files('community-links/'.$page->id);
                $size = array_sum(array_map(fn ($file) => Storage::disk('local')->size($file), $files));
                if (count($files) >= 10 || $size + strlen($bytes) > 10485760) {
                    throw ValidationException::withMessages(['avatar' => 'Kuota gambar halaman penuh. Hubungi pengelola untuk membersihkan gambar lama.']);
                }
                $asset = (string) Str::uuid();
                $path = 'community-links/'.$page->id.'/'.$asset.'.webp';
                if (! Storage::disk('local')->put($path, $bytes)) {
                    throw new \RuntimeException('Penyimpanan gambar gagal. Draft belum berubah.');
                }
                $content = $page->draft_content;
                $content[$kind] = $asset;
                $page->update(['draft_content' => $content, 'revision' => $page->revision + 1]);
                ActivityLog::create(['user_id' => $actor->id, 'event' => 'updated', 'auditable_type' => CommunityLinkPage::class, 'auditable_id' => $page->id, 'new_values' => ['action' => 'upload_'.$kind, 'revision' => $page->revision]]);
            });
        } catch (\Throwable $e) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }
}

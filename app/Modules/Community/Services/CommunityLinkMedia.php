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
    private function modelBytes(UploadedFile $file): string
    {
        $fail = static function (): never {
            throw ValidationException::withMessages(['avatar' => 'GLB 2.0 mandiri tidak valid atau melebihi batas model. Gunakan tekstur PNG/JPEG tertanam, tanpa URI atau ekstensi.']);
        };
        if ($file->getSize() > 5242880 || strtolower($file->getClientOriginalExtension()) !== 'glb') {
            $fail();
        }
        $bytes = file_get_contents($file->getRealPath());
        $length = strlen($bytes);
        if ($length < 28 || substr($bytes, 0, 4) !== 'glTF') {
            $fail();
        }
        $header = unpack('Vversion/Vlength', substr($bytes, 4, 8));
        if ($header['version'] !== 2 || $header['length'] !== $length) {
            $fail();
        }
        $chunks = [];
        for ($offset = 12; $offset < $length;) {
            if ($offset + 8 > $length || count($chunks) >= 2) {
                $fail();
            }
            $chunk = unpack('Vlength/Vtype', substr($bytes, $offset, 8));
            $offset += 8;
            if ($chunk['length'] % 4 !== 0 || $offset + $chunk['length'] > $length || $chunk['type'] !== (count($chunks) === 0 ? 0x4E4F534A : 0x004E4942)) {
                $fail();
            }
            $chunks[] = substr($bytes, $offset, $chunk['length']);
            $offset += $chunk['length'];
        }
        try {
            $json = json_decode($chunks[0] ?? '', true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $fail();
        }
        if (! is_array($json) || ($json['asset']['version'] ?? null) !== '2.0') {
            $fail();
        }
        $inspect = static function ($value) use (&$inspect, $fail): void {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    if ($key === 'uri' || ($key === 'extensions' && ! empty($item))) {
                        $fail();
                    }
                    $inspect($item);
                }
            } elseif (is_float($value) && ! is_finite($value)) {
                $fail();
            }
        };
        $inspect($json);
        if (! empty($json['extensionsUsed']) || ! empty($json['extensionsRequired'])) {
            $fail();
        }
        foreach (['buffers' => 1, 'bufferViews' => 1024, 'accessors' => 1024, 'meshes' => 100, 'nodes' => 100, 'materials' => 32, 'textures' => 8, 'images' => 8, 'animations' => 8, 'scenes' => 8, 'skins' => 32] as $key => $max) {
            if (isset($json[$key]) && (! is_array($json[$key]) || ! array_is_list($json[$key]) || count($json[$key]) > $max)) {
                $fail();
            }
            foreach ($json[$key] ?? [] as $entry) {
                if (! is_array($entry)) {
                    $fail();
                }
            }
        }
        $bin = $chunks[1] ?? '';
        $bufferSize = $json['buffers'][0]['byteLength'] ?? null;
        if (! is_int($bufferSize) || $bufferSize < 1 || $bufferSize > strlen($bin) || strlen($bin) - $bufferSize > 3) {
            $fail();
        }
        foreach ($json['bufferViews'] ?? [] as $view) {
            $start = $view['byteOffset'] ?? 0;
            $size = $view['byteLength'] ?? null;
            if (($view['buffer'] ?? null) !== 0 || ! is_int($start) || $start < 0 || ! is_int($size) || $size < 1 || $start + $size > $bufferSize) {
                $fail();
            }
            if (isset($view['byteStride']) && (! is_int($view['byteStride']) || $view['byteStride'] < 4 || $view['byteStride'] > 252 || $view['byteStride'] % 4 !== 0)) {
                $fail();
            }
        }
        $components = [5120 => 1, 5121 => 1, 5122 => 2, 5123 => 2, 5125 => 4, 5126 => 4];
        $types = ['SCALAR' => 1, 'VEC2' => 2, 'VEC3' => 3, 'VEC4' => 4, 'MAT4' => 16];
        $total = 0;
        foreach ($json['accessors'] ?? [] as $accessor) {
            $count = $accessor['count'] ?? 0;
            $component = $components[$accessor['componentType'] ?? 0] ?? 0;
            $width = $types[$accessor['type'] ?? ''] ?? 0;
            $view = $json['bufferViews'][$accessor['bufferView'] ?? -1] ?? null;
            $start = $accessor['byteOffset'] ?? 0;
            if (! is_int($count) || $count < 1 || $count > 300000 || ! $component || ! $width || ! $view || isset($accessor['sparse']) || ! is_int($start) || $start < 0) {
                $fail();
            }
            $stride = $view['byteStride'] ?? ($component * $width);
            if ($stride < $component * $width || $start + ($count - 1) * $stride + $component * $width > $view['byteLength']) {
                $fail();
            }
            if ($accessor['componentType'] === 5126) {
                for ($i = 0; $i < $count; $i++) {
                    $values = unpack('g*', substr($bin, ($view['byteOffset'] ?? 0) + $start + $i * $stride, $component * $width));
                    foreach ($values as $value) {
                        if (! is_finite($value)) {
                            $fail();
                        }
                    }
                }
            }
            $total += $count;
            if ($total > 1000000) {
                $fail();
            }
        }
        $vertices = 0;
        $primitives = 0;
        foreach ($json['meshes'] ?? [] as $mesh) {
            if (! isset($mesh['primitives']) || ! is_array($mesh['primitives'])) {
                $fail();
            }
            foreach ($mesh['primitives'] as $primitive) {
                if (! is_array($primitive) || ! is_array($primitive['attributes'] ?? null) || ++$primitives > 100) {
                    $fail();
                }
                $position = $json['accessors'][$primitive['attributes']['POSITION'] ?? -1] ?? null;
                if (! $position || $position['type'] !== 'VEC3' || $position['componentType'] !== 5126) {
                    $fail();
                }
                $vertices += $position['count'];
                foreach ($primitive['attributes'] as $index) {
                    if (! is_int($index) || ! isset($json['accessors'][$index])) {
                        $fail();
                    }
                }
                if (isset($primitive['targets']) && count($primitive['targets']) > 8) {
                    $fail();
                }
            }
        }
        if ($vertices < 1 || $vertices > 100000) {
            $fail();
        }
        foreach ($json['images'] ?? [] as $image) {
            $view = $json['bufferViews'][$image['bufferView'] ?? -1] ?? null;
            if (! $view || ! in_array($image['mimeType'] ?? '', ['image/png', 'image/jpeg'], true)) {
                $fail();
            }
            $info = @getimagesizefromstring(substr($bin, $view['byteOffset'] ?? 0, $view['byteLength']));
            if (! $info || $info['mime'] !== $image['mimeType'] || $info[0] > 2048 || $info[1] > 2048) {
                $fail();
            }
        }
        $parents = [];
        $instances = 0;
        foreach ($json['nodes'] ?? [] as $node) {
            if (isset($node['mesh'])) {
                foreach ($json['meshes'][$node['mesh']]['primitives'] ?? [] as $primitive) {
                    $instances += $json['accessors'][$primitive['attributes']['POSITION']]['count'];
                }
            }
            if ($instances > 100000 || ! is_array($node['children'] ?? [])) {
                $fail();
            }
            foreach ($node['children'] ?? [] as $child) {
                if (! is_int($child) || isset($parents[$child])) {
                    $fail();
                }
                $parents[$child] = true;
            }
        }
        $visited = [];
        $walk = static function ($index, array $path = []) use (&$walk, &$visited, $json, $fail): void {
            if (! is_int($index) || ! isset($json['nodes'][$index]) || isset($path[$index]) || count($path) >= 64) {
                $fail();
            }
            if (isset($visited[$index])) {
                return;
            }
            $path[$index] = true;
            $node = $json['nodes'][$index];
            if (isset($node['mesh']) && (! is_int($node['mesh']) || ! isset($json['meshes'][$node['mesh']]))) {
                $fail();
            }
            if (! is_array($node['children'] ?? [])) {
                $fail();
            }
            foreach ($node['children'] ?? [] as $child) {
                $walk($child, $path);
            }
            $visited[$index] = true;
        };
        foreach (array_keys($json['nodes'] ?? []) as $index) {
            $walk($index);
        }
        foreach ($json['scenes'] ?? [] as $entry) {
            $roots = $entry['nodes'] ?? [];
            if (! is_array($roots) || count($roots) > 100) {
                $fail();
            }
            $unique = [];
            foreach ($roots as $root) {
                if (! is_int($root) || isset($unique[$root]) || isset($parents[$root])) {
                    $fail();
                }
                $unique[$root] = true;
                $walk($root);
            }
        }
        foreach ($json['animations'] ?? [] as $animation) {
            foreach (['channels', 'samplers'] as $key) {
                if (! is_array($animation[$key] ?? null) || count($animation[$key]) > 100) {
                    $fail();
                }
            }
        }
        foreach ($json['skins'] ?? [] as $skin) {
            if (! is_array($skin['joints'] ?? null) || count($skin['joints']) > 100) {
                $fail();
            }
        }
        $scene = $json['scenes'][$json['scene'] ?? 0] ?? null;
        if (! is_array($scene) || empty($scene['nodes']) || ! is_array($scene['nodes'])) {
            $fail();
        }
        foreach ($scene['nodes'] as $index) {
            $walk($index);
        }

        return $bytes;
    }

    public function upload(Community $community, CommunityLinkPage $page, User $actor, UploadedFile $file, int $revision, string $kind = 'avatar'): void
    {
        abort_unless(in_array($kind, ['avatar', 'background', 'model'], true), 422);
        if ($kind === 'model') {
            try {
                $bytes = $this->modelBytes($file);
            } catch (\TypeError|\ValueError $e) {
                throw ValidationException::withMessages(['avatar' => 'Struktur GLB tidak valid.']);
            }
        } else {
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
        }
        $path = null;
        try {
            DB::transaction(function () use ($community, $page, $actor, $revision, $bytes, $kind, &$path): void {
                $community = Community::whereKey($community->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
                abort_unless($actor->fresh()?->is_active, 403);
                Gate::forUser($actor)->authorize('update', $community);
                $page = CommunityLinkPage::whereKey($page->id)->where('community_id', $community->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $page->revision === $revision, 409, 'Halaman berubah. Muat ulang.');
                if ($kind === 'model') {
                    abort_unless(app(CommunityLinkPageService::class)->hasPremiumAccess($community), 403);
                }
                $files = Storage::disk('local')->files('community-links/'.$page->id);
                $size = array_sum(array_map(fn ($file) => Storage::disk('local')->size($file), $files));
                if (count($files) >= 10 || $size + strlen($bytes) > 10485760) {
                    throw ValidationException::withMessages(['avatar' => 'Kuota media halaman penuh. Hubungi pengelola untuk membersihkan gambar lama.']);
                }
                $asset = (string) Str::uuid();
                $path = 'community-links/'.$page->id.'/'.$asset.($kind === 'model' ? '.glb' : '.webp');
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

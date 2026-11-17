<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Http\Requests\SocialRelayMonitorRequest;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Internal read-only monitor: every collected comment from arrival at Core to the reply result. No actions. */
final class SocialRelayMonitorController extends Controller
{
    public const STATUSES = [
        'masuk' => 'Masuk ke ActStation', 'di_office' => 'Diambil Office', 'terjadwal' => 'Terjadwal kirim', 'dieksekusi' => 'Sedang dikirim n8n',
        'terkirim' => 'Terbalas', 'gagal' => 'Gagal', 'tidak_pasti' => 'Tidak pasti, cek manual',
    ];

    public const PLATFORMS = ['instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'];

    public function index(SocialRelayMonitorRequest $request): View
    {
        $filters = $request->validated();
        $base = DB::table('social_relay_traces');
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $query = (clone $base)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['platform'] ?? null, fn ($q, $platform) => $q->where('platform', $platform))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('received_at', '>=', $from.' 00:00:00'))
            ->when($filters['until'] ?? null, fn ($q, $until) => $q->where('received_at', '<=', $until.' 23:59:59'))
            ->when(trim((string) ($filters['search'] ?? '')) !== '', function ($q) use ($filters): void {
                // Texts are encrypted at rest; search covers public identifiers only.
                $term = ltrim(trim($filters['search']), '@');
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
                $q->where(fn ($q) => $q->where('username', 'like', $like)->orWhere('comment_id', $term)->orWhere('media_id', $term)->orWhere('reply_id', $term));
            });
        $traces = $query->orderByDesc('received_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $traces->getCollection()->transform(function (object $row): object {
            foreach (['comment_text', 'reply_text', 'dm_text'] as $field) {
                try {
                    $row->{$field} = $row->{$field} === null ? null : Crypt::decryptString($row->{$field});
                } catch (DecryptException) {
                    $row->{$field} = null;
                }
            }

            return $row;
        });

        return view('admin.social-relay.index', ['traces' => $traces, 'counts' => $counts, 'filters' => $filters,
            'statuses' => self::STATUSES, 'platforms' => self::PLATFORMS]);
    }
}

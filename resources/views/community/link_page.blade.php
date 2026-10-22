<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $content['title'] }} — ActStation</title>
<meta name="description" content="{{ $content['bio'] ?? '' }}">
@if($preview)<meta name="robots" content="noindex,nofollow">@endif
<link rel="canonical" href="{{ rtrim(config('app.url'), '/') . '/c/' . $page->handle }}">
<meta property="og:title" content="{{ $content['title'] }}"><meta property="og:description" content="{{ $content['bio'] ?? '' }}">
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;font:16px/1.6 system-ui,sans-serif;background:var(--bg);color:var(--fg)}main{width:min(100% - 32px,640px);margin:0 auto;padding:40px 0}h1{line-height:1.2;overflow-wrap:anywhere}p{white-space:pre-line;overflow-wrap:anywhere}a{color:inherit}button,input{font:inherit;min-height:44px}button{cursor:pointer}button:focus-visible,a:focus-visible,input:focus-visible{outline:3px solid var(--accent);outline-offset:4px}.profile{text-align:center}.avatar{width:100px;height:100px;object-fit:cover;border-radius:50%}.badge{display:inline-block;padding:4px 12px;border:1px solid currentColor;border-radius:24px;font-size:13px}.filters{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0}.filters button{max-width:100%;overflow-wrap:anywhere;border:1px solid var(--accent);background:transparent;color:inherit;border-radius:24px;padding:8px 16px}.filters button[aria-pressed=true]{background:var(--accent);color:var(--button-text)}input{width:100%;padding:12px;border:1px solid #64748b;border-radius:12px;background:#fff;color:#0f172a}.link{display:block;margin:14px 0;padding:18px;border-radius:16px;background:var(--accent);color:var(--button-text);text-decoration:none;overflow-wrap:anywhere;box-shadow:0 4px 12px #0001}.link small{display:block}.domain{font-size:12px;opacity:.9}.socials{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}.socials a{padding:10px}.foot{text-align:center;font-size:13px;margin-top:32px}.notice{padding:12px;border:1px solid currentColor;border-radius:12px}.gradient{background:linear-gradient(135deg,#e0e7ff,#fae8ff,#dbeafe)}.soft-pastel .link{border-radius:32px}.sport-bold h1{text-transform:uppercase}.sport-bold .link{border-radius:0;border:3px solid #171717;box-shadow:5px 5px 0 #171717}.midnight .link{border:1px solid #38bdf8;box-shadow:0 0 24px #38bdf822}.photo{background:linear-gradient(135deg,#e7e5e4,#a8a29e)}.photo main{background:#fffffff0;padding:32px 20px;margin-top:24px;border-radius:24px}.perinatologi-care{background:linear-gradient(135deg,#f0fdfa,#fff1f2)}.perinatologi-care .link{background:#fff;color:#134e4a;border:1px solid #99f6e4;border-left:6px solid #0f766e}.perinatologi-care .badge{background:#ccfbf1}.hidden,[hidden]{display:none!important}@media(max-width:360px){main{width:calc(100% - 24px);padding-top:24px}.filters button{padding:8px 12px}}
</style>
<link rel="stylesheet" href="{{ asset('assets/community-links/share.css') }}?v={{ filemtime(public_path('assets/community-links/share.css')) }}">
@php
$themeId = isset($themes[$content['theme_id'] ?? '']) ? $content['theme_id'] : 'clean-light';
$theme = $themes[$themeId];
$custom = $themeId === 'perinatologi-care' && !empty($content['theme_options']) ? $content['theme_options'] : [];
$custom = $custom ? $custom + \App\Modules\Community\Services\CommunityLinkPageService::THEME_OPTIONS : [];
$customStyle = '';
foreach (['background' => 'bg', 'text' => 'text', 'button' => 'button', 'button_text' => 'button-text', 'emblem_color' => 'emblem'] as $key => $variable) {
    if (isset($custom[$key]) && is_string($custom[$key]) && preg_match('/^#[0-9a-fA-F]{6}$/', $custom[$key]) && strcasecmp($custom[$key], \App\Modules\Community\Services\CommunityLinkPageService::THEME_OPTIONS[$key]) !== 0) {
        $customStyle .= '--custom-'.$variable.':'.$custom[$key].';';
    }
}
if ($custom && (int) $custom['radius'] !== \App\Modules\Community\Services\CommunityLinkPageService::THEME_OPTIONS['radius']) $customStyle .= '--custom-radius:'.max(0, min(32, (int) $custom['radius'])).'px;' ;
if ($custom && (int) $custom['emblem_size'] !== 88) $customStyle .= '--emblem-size:'.max(64, min(240, (int) $custom['emblem_size'])).'px;';
$customKeys = implode(' ', array_keys(array_filter($custom, fn ($value, $key) => strtolower((string) $value) !== strtolower((string) \App\Modules\Community\Services\CommunityLinkPageService::THEME_OPTIONS[$key]), ARRAY_FILTER_USE_BOTH)));
$imageEmblem = $themeId === 'perinatologi-care' && ($custom['emblem_shape'] ?? '') === 'image';
$imageAsset = $imageEmblem ? ($content['avatar'] ?? null) : null;
$imageUrl = $imageAsset ? ($preview ? route('panel.community-links.avatar-preview', [$page->id, $imageAsset]) : route('community-links.avatar', [$page->handle, $imageAsset])) : null;
$modelAsset = $themeId === 'perinatologi-care' && !$imageEmblem ? ($content['model'] ?? null) : null;
$lightButton = in_array($themeId, ['midnight']);
$visibleCategories = collect($content['categories'] ?? [])->filter(fn($c) => $c['visible'] ?? true);
$links = collect($content['links'] ?? [])->filter(fn($link) => !empty($link['visible']) && (empty($link['category_id']) || $visibleCategories->contains('id', $link['category_id'])));
$visibleCategories = $visibleCategories->filter(fn($category) => $links->contains('category_id', $category['id']));
@endphp
@if($themeId === 'perinatologi-care')
<link rel="stylesheet" href="{{ asset('assets/community-links/perinatologi-care.css') }}?v={{ filemtime(public_path('assets/community-links/perinatologi-care.css')) }}">
<script defer src="{{ asset('assets/community-links/three-r128.min.js') }}"></script>
@if($imageUrl)<script defer src="{{ asset('assets/community-links/image-emblem.js') }}?v={{ filemtime(public_path('assets/community-links/image-emblem.js')) }}"></script>@endif
@if($modelAsset)<script defer src="{{ asset('assets/community-links/GLTFLoader-r128.js') }}?v={{ filemtime(public_path('assets/community-links/GLTFLoader-r128.js')) }}"></script>@endif
<script defer src="{{ asset('assets/community-links/perinatologi-care.js') }}?v={{ filemtime(public_path('assets/community-links/perinatologi-care.js')) }}"></script>
@endif
</head>
<body class="{{ $themeId }}" @if($custom) data-custom-theme="{{ $customKeys }}" data-motion-default="{{ $custom['motion_enabled'] ? '1' : '0' }}" data-emblem-shape="{{ $custom['emblem_shape'] }}" data-emblem-color="{{ $custom['emblem_color'] }}" data-emblem-size="{{ $custom['emblem_size'] }}" data-emblem-depth="{{ $custom['emblem_depth'] }}" data-emblem-speed="{{ $custom['emblem_speed'] }}" @endif style="--bg:{{ $theme['background'] }};--fg:{{ $theme['color'] }};--accent:{{ $theme['accent'] }};--button-text:{{ $lightButton ? '#0f172a' : '#ffffff' }};{{ $customStyle }}">
@if($themeId === 'perinatologi-care')<canvas id="bg-3d-canvas" aria-hidden="true"></canvas>@endif
@if($themeId === 'photo' && !empty($content['background']))
<img src="{{ $preview ? route('panel.community-links.avatar-preview', [$page->id, $content['background']]) : route('community-links.avatar', [$page->handle, $content['background']]) }}" alt="" style="position:fixed;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;pointer-events:none">
@endif
<main style="position:relative;z-index:1">
@if($preview)<p class="notice">{{ $themeId === 'perinatologi-care' ? 'Preview draft · hanya untuk Anda.' : 'Preview privat draft — belum tentu sama dengan halaman publik. Kunjungan dan klik preview tidak dihitung.' }}</p>@endif
<header class="profile">
@if($themeId === 'perinatologi-care' && ($custom['emblem_enabled'] ?? true))
<div class="perina-hero" @if($modelAsset || $imageUrl) data-uploaded-model @endif>
<div class="perina-emblem">
<div id="hero-fallback" class="perina-static" aria-hidden="true">@if($imageUrl)<img src="{{ $imageUrl }}" alt="" width="72" height="72" style="object-fit:contain">@else<svg viewBox="0 0 64 64" fill="{{ $custom['emblem_color'] ?? '#f43f5e' }}">@if(($custom['emblem_shape'] ?? '') === 'sphere')<circle cx="32" cy="32" r="25"/>@elseif(($custom['emblem_shape'] ?? '') === 'diamond')<path d="M32 4 59 32 32 60 5 32Z"/>@else<path d="M32 55 9 32C-7 12 18-1 32 15 46-1 71 12 55 32Z"/>@endif</svg>@endif</div>
<canvas id="hero-3d-canvas" @if($imageUrl) data-image-emblem-url="{{ $imageUrl }}" @endif @if($modelAsset) data-model-url="{{ $preview ? route('panel.community-links.model-preview', [$page->id, $modelAsset]) : route('community-links.model', [$page->handle, $modelAsset]) }}" @endif width="152" height="152" aria-hidden="true" hidden></canvas>
</div>
</div>
@endif
@if(!empty($content['avatar']) && (!$imageUrl || !($custom['emblem_enabled'] ?? true)))<img class="avatar" alt="Logo {{ $content['title'] }}" src="{{ $preview ? route('panel.community-links.avatar-preview', [$page->id, $content['avatar']]) : route('community-links.avatar', [$page->handle, $content['avatar']]) }}">@endif
<h1>{{ $content['title'] }}</h1>
@if($themeId !== 'perinatologi-care')<p>{{ $community->name }}</p>
<span class="badge">{{ $community->status === 'verified' ? 'Komunitas terverifikasi' : 'Legalitas belum terverifikasi' }}</span>
@endif
@if(!empty($content['bio']))<p>{{ $content['bio'] }}</p>@endif
<div class="socials">@foreach($content['socials'] ?? [] as $social)<a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer ugc nofollow">{{ $social['label'] }}</a>@endforeach</div>
@if($themeId !== 'perinatologi-care')<button type="button" id="share">Salin Tautan</button><p id="share-status" role="status"></p>@endif
</header>
<section aria-label="Daftar tautan">
@if($themeId === 'perinatologi-care' && $visibleCategories->isNotEmpty())<details class="perina-discovery" @if($links->count() > 5) open @endif><summary>Cari atau pilih kategori</summary>@endif
<label for="search">Cari tautan</label><input id="search" type="search" placeholder="Cari informasi...">
@if($visibleCategories->isNotEmpty())
<div class="filters" aria-label="Kategori tautan">
<button type="button" data-category="all" aria-pressed="true">Semua</button>
@foreach($visibleCategories as $category)
@if($links->contains('category_id', $category['id']))<button type="button" data-category="{{ $category['id'] }}" aria-pressed="false">{{ $category['name'] }}</button>@endif
@endforeach
@if($links->contains(fn($link) => empty($link['category_id'])))<button type="button" data-category="general" aria-pressed="false">Umum</button>@endif
</div>
@endif
@if($themeId === 'perinatologi-care' && $visibleCategories->isNotEmpty())</details>@endif
<div id="links">
@foreach($links as $link)
<div class="link-entry" data-link data-category="{{ $link['category_id'] ?? 'general' }}">
<a class="link" href="{{ $preview ? $link['url'] : route('community-links.click', [$page->handle, $link['id']]) }}" target="_blank" rel="noopener noreferrer ugc nofollow"><strong>{{ $link['label'] }}</strong>@if(!empty($link['subtitle']))<small>{{ $link['subtitle'] }}</small>@endif @if($themeId !== 'perinatologi-care')<span class="domain">{{ parse_url($link['url'], PHP_URL_HOST) }} · buka tab baru</span>@else<span class="perina-sr-only"> (buka tab baru)</span>@endif</a>
<button type="button" class="link-share-button" data-share-url="{{ $link['url'] }}" data-share-title="{{ $link['label'] }}" aria-label="Bagikan {{ $link['label'] }}" title="Bagikan tautan" hidden><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-4M8.6 13.5l6.8 4"/></svg></button>
</div>
@endforeach
</div>
<p id="empty" hidden>Tidak ada tautan yang cocok. <button type="button" id="reset">Reset Filter</button></p>
</section>
<footer class="foot">@if($themeId === 'perinatologi-care')
<div class="perina-footer-actions"><button type="button" id="share">Salin Tautan</button><button id="motion-toggle" type="button" aria-pressed="false" hidden><span id="motion-label">Aktifkan animasi</span></button></div><p id="share-status" role="status"></p>
@else Dikelola komunitas. Tautan eksternal bukan garansi ActStation.<br>@endif<a href="{{ url('/') }}">ActStation</a></footer>
</main>
<dialog id="link-share-dialog" aria-labelledby="link-share-heading">
<div class="link-share-heading"><h2 id="link-share-heading">Bagikan tautan</h2><form method="dialog"><button type="submit" aria-label="Tutup menu bagikan">Tutup</button></form></div>
<p id="link-share-title"></p>
<label for="link-share-url">Alamat tautan</label><input id="link-share-url" type="url" readonly>
<div class="link-share-platforms">
<button id="link-share-copy" type="button" autofocus>Salin tautan</button>
@foreach(['whatsapp' => 'WhatsApp', 'facebook' => 'Facebook', 'x' => 'X', 'linkedin' => 'LinkedIn', 'email' => 'Email'] as $platform => $label)
<a data-share-platform="{{ $platform }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" aria-label="{{ $label }} (buka aplikasi atau tab baru)">{{ $label }}</a>
@endforeach
<button id="link-share-native" type="button" hidden>Lainnya</button>
</div><p id="link-share-feedback" role="status" aria-live="polite"></p>
</dialog>
<script defer src="{{ asset('assets/community-links/share.js') }}?v={{ filemtime(public_path('assets/community-links/share.js')) }}"></script>
<script>
(()=>{let category='all';const search=document.querySelector('#search'),rows=[...document.querySelectorAll('[data-link]')],buttons=[...document.querySelectorAll('button[data-category]')];function filter(){const q=search.value.trim().toLocaleLowerCase('id');let count=0;rows.forEach(row=>{row.hidden=!((category==='all'||row.dataset.category===category)&&row.textContent.toLocaleLowerCase('id').includes(q));if(!row.hidden)count++});document.querySelector('#empty').hidden=count>0;buttons.forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.category===category)))}buttons.forEach(button=>button.addEventListener('click',()=>{category=button.dataset.category;filter()}));search.addEventListener('input',filter);document.querySelector('#reset').addEventListener('click',()=>{category='all';search.value='';filter()});document.querySelector('#share').addEventListener('click',async()=>{const url=document.querySelector('link[rel=canonical]').href;try{await navigator.clipboard.writeText(url);document.querySelector('#share-status').textContent='Tautan disalin.'}catch{document.querySelector('#share-status').textContent=url}})})();
</script>
</body></html>

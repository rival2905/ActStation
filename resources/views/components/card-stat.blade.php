@props([
    'title',
    'value',
    'icon',
    'color' => 'primary',
    'badge' => null,
])

<div class="card h-100">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="text-secondary mb-0 text-uppercase fs-11 fw-bold">{{ $title }}</h6>
            <div class="bg-{{ $color }}-subtle text-{{ $color }} rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <span class="{{ $icon }} fs-9"></span>
            </div>
        </div>
        <div class="d-flex align-items-baseline justify-content-between">
            <h3 class="mb-0 fw-bold">{{ $value }}</h3>
            @if($badge)
                <span class="badge rounded-pill badge-subtle-{{ $color }} fs-11">{{ $badge }}</span>
            @endif
        </div>
    </div>
</div>

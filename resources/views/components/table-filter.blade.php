@props([
    'action',
    'resetUrl',
])

<div class="card mb-3">
    <div class="card-body bg-body-tertiary">
        <form method="GET" action="{{ $action }}" class="row g-2 align-items-center">
            {{ $slot }}
            <div class="col-auto ms-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <span class="fas fa-filter me-1"></span> Filter
                </button>
                <a href="{{ $resetUrl }}" class="btn btn-sm btn-outline-secondary">
                    <span class="fas fa-undo me-1"></span> Reset
                </a>
            </div>
        </form>
    </div>
</div>

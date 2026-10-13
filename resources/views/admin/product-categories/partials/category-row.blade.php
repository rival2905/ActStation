<tr>
    <td class="align-middle">
        <div class="d-flex align-items-center" style="padding-left: {{ ($level ?? 0) * 24 }}px;">
            @if(($level ?? 0) > 0)
                <span class="text-400 me-2 font-monospace">└──</span>
            @endif
            @if($category->icon)
                <span class="{{ $category->icon }} text-primary me-2"></span>
            @else
                <span class="fas fa-folder text-warning me-2"></span>
            @endif
            <span class="fw-bold text-1000">{{ $category->name }}</span>
        </div>
    </td>
    <td class="align-middle font-monospace text-500 fs-11">{{ $category->slug }}</td>
    <td class="align-middle text-center">
        <span class="badge badge-subtle-secondary">{{ $category->sort_order }}</span>
    </td>
    <td class="align-middle text-center">
        <span class="badge badge-subtle-info fs-11">
            {{ $category->children ? $category->children->count() : 0 }} Subkategori
        </span>
    </td>
    <td class="align-middle text-end text-nowrap">
        <div class="d-inline-flex gap-1">
            @can('product-category.update')
            <a href="{{ route('admin.product-categories.edit', $category) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                <span class="fas fa-edit"></span>
            </a>
            @endcan
            @can('product-category.delete')
            <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteCategoryModal" data-action="{{ route('admin.product-categories.destroy', $category) }}">
                <span class="fas fa-trash-alt"></span>
            </button>
            @endcan
        </div>
    </td>
</tr>
@if($category->children && $category->children->isNotEmpty())
    @foreach($category->children as $child)
        @include('admin.product-categories.partials.category-row', ['category' => $child, 'level' => ($level ?? 0) + 1])
    @endforeach
@endif

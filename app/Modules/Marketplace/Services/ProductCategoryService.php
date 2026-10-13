<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Services;

use App\Modules\Marketplace\Models\ProductCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductCategoryService
{
    /**
     * @param  Collection<int, ProductCategory>|null  $categories
     * @return Collection<int, ProductCategory>
     */
    public function getTree(?Collection $categories = null): Collection
    {
        // ponytail: single query in-memory tree; upgrade to nested set if category count > 1000
        $all = $categories ?? ProductCategory::orderBy('sort_order')->orderBy('name')->get();
        $grouped = $all->groupBy('parent_id');

        foreach ($all as $category) {
            $category->setRelation('children', $grouped->get($category->id, new Collection));
        }

        /** @var Collection<int, ProductCategory> */
        return $grouped->get(null, new Collection);
    }

    public function assertNoCycle(int $categoryId, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $categoryId) {
            throw new InvalidArgumentException('Siklus kategori terdeteksi: kategori tidak dapat menjadi induk bagi dirinya sendiri.');
        }

        $visited = [];
        $currentParentId = $parentId;

        while ($currentParentId !== null) {
            if ($currentParentId === $categoryId) {
                throw new InvalidArgumentException('Siklus kategori terdeteksi: kategori induk merupakan turunan dari kategori ini.');
            }

            if (isset($visited[$currentParentId])) {
                throw new InvalidArgumentException('Siklus kategori terdeteksi pada struktur hierarki.');
            }
            $visited[$currentParentId] = true;

            /** @var ProductCategory|null $ancestor */
            $ancestor = ProductCategory::find($currentParentId);
            if (! $ancestor) {
                break;
            }

            $currentParentId = $ancestor->parent_id;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProductCategory
    {
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug((string) $data['name']);
        }

        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;
        $data['parent_id'] = $parentId;

        return ProductCategory::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProductCategory $category, array $data): ProductCategory
    {
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;
        $data['parent_id'] = $parentId;

        $this->assertNoCycle($category->id, $parentId);

        if (isset($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = $this->generateUniqueSlug((string) $data['name'], $category->id);
        }

        $category->update($data);

        return $category;
    }

    public function delete(ProductCategory $category): void
    {
        $category->delete();
    }

    public function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $i = 1;

        while (ProductCategory::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        return $slug;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Services;

use App\Modules\Marketplace\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $uploadedImages
     */
    public function update(Product $product, array $data, array $uploadedImages = []): Product
    {
        if (isset($data['name']) && $data['name'] !== $product->name) {
            $data['slug'] = $this->generateUniqueSlug((string) $data['name'], $product->id);
        }

        if (! empty($uploadedImages)) {
            // Delete old images
            if (is_array($product->images)) {
                foreach ($product->images as $oldImage) {
                    if (is_string($oldImage)) {
                        Storage::disk('public')->delete($oldImage);
                    }
                }
            }

            // Store new images
            $savedPaths = [];
            foreach ($uploadedImages as $image) {
                if ($image instanceof UploadedFile) {
                    $savedPaths[] = $image->store('products', 'public');
                }
            }
            $data['images'] = $savedPaths;
        } else {
            // Keep existing images, do not overwrite with null
            unset($data['images']);
        }

        $product->update($data);

        return $product;
    }

    public function moderate(Product $product, string $status): Product
    {
        $allowedStatuses = ['draft', 'active', 'out_of_stock', 'archived'];
        if (! in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException("Status produk '{$status}' tidak valid.");
        }

        $product->update([
            'status' => $status,
        ]);

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $uploadedImages
     */
    public function create(array $data, array $uploadedImages = []): Product
    {
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug((string) $data['name']);
        }

        if (! empty($uploadedImages)) {
            $savedPaths = [];
            foreach ($uploadedImages as $image) {
                if ($image instanceof UploadedFile) {
                    $savedPaths[] = $image->store('products', 'public');
                }
            }
            $data['images'] = $savedPaths;
        }

        return Product::create($data);
    }

    public function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $i = 1;

        while (Product::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        return $slug;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gallery extends Model
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'cover_image',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(GalleryMedia::class, 'gallery_id')->orderBy('sort_order');
    }
}

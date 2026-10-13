<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryMedia extends Model
{
    use Auditable;

    /**
     * @var string
     */
    protected $table = 'gallery_media';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gallery_id',
        'type',
        'file_path',
        'caption',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'gallery_id');
    }
}

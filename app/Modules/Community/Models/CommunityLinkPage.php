<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityLinkPage extends Model
{
    protected $fillable = ['community_id', 'handle', 'is_primary', 'status', 'draft_content', 'published_content', 'revision', 'published_at', 'suspended_at', 'suspended_by', 'suspension_reason'];

    protected function casts(): array
    {
        return ['draft_content' => 'array', 'published_content' => 'array', 'is_primary' => 'boolean', 'published_at' => 'datetime', 'suspended_at' => 'datetime'];
    }
}

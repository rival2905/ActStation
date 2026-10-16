<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEducation extends Model
{
    protected $table = 'user_educations';

    protected $fillable = ['user_id', 'community_id', 'institution_name', 'education_type', 'identifier_type', 'identifier_value', 'education_detail'];

    protected $hidden = ['identifier_value'];

    protected function casts(): array
    {
        return ['identifier_value' => 'encrypted'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

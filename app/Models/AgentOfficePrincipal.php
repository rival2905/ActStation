<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User;
use Laravel\Sanctum\HasApiTokens;

class AgentOfficePrincipal extends User
{
    use HasApiTokens;

    protected $table = 'agent_office_principals';

    protected $fillable = ['name', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}

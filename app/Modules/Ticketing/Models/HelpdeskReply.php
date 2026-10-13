<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Models;

use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskReply extends Model
{
    use Auditable;

    protected $table = 'helpdesk_replies';

    protected $fillable = [
        'helpdesk_ticket_id',
        'user_id',
        'body',
    ];

    public function helpdeskTicket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'helpdesk_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

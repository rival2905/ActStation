<?php

declare(strict_types=1);

namespace App\Modules\Community\Exceptions;

use RuntimeException;

class InvalidInvitationException extends RuntimeException
{
    protected $message = 'Undangan tidak ditemukan atau sudah tidak berlaku.';
}

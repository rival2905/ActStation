<?php

declare(strict_types=1);

namespace App\Modules\Event\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrationGuardException extends Exception
{
    public function __construct(string $message = 'Pendaftaran tidak memenuhi kriteria validasi.', int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function render(Request $request): Response|RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
            ], 422);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}

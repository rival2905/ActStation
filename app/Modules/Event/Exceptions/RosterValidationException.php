<?php

declare(strict_types=1);

namespace App\Modules\Event\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RosterValidationException extends Exception
{
    /**
     * @param  array<string, list<string>|string>  $errors
     */
    public function __construct(
        string $message = 'Validasi roster gagal.',
        public readonly array $errors = [],
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }

    public function render(Request $request): Response|RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors' => $this->errors,
            ], 422);
        }

        return back()->withInput()->withErrors($this->errors)->with('error', $this->getMessage());
    }
}

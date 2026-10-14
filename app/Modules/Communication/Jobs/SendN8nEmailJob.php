<?php

declare(strict_types=1);

namespace App\Modules\Communication\Jobs;

use App\Modules\Communication\Models\EmailMessage;
use App\Modules\Communication\Services\N8nEmailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendN8nEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int|string $messageId)
    {
        $this->onConnection('database')->onQueue('emails')->afterCommit();
    }

    public function failed(?Throwable $exception): void
    {
        EmailMessage::query()->whereKey($this->messageId)
            ->where('status', EmailMessage::STATUS_PROCESSING)
            ->update([
                'status' => EmailMessage::STATUS_UNKNOWN,
                'failed_at' => now(),
                'error_message' => 'Worker interrupted: delivery unknown, manual review required',
            ]);
    }

    public function handle(?N8nEmailSender $sender = null): void
    {
        $sender ??= app(N8nEmailSender::class);
        $sender->send($this->messageId);
    }
}

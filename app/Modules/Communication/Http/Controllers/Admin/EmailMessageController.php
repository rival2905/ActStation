<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers\Admin;

use App\Contracts\EmailDelivery;
use App\Http\Controllers\Controller;
use App\Modules\Communication\Http\Requests\RetryEmailRequest;
use App\Modules\Communication\Http\Requests\SendEmailRequest;
use App\Modules\Communication\Models\EmailMessage;
use App\Modules\Communication\Services\N8nEmailSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;

class EmailMessageController extends Controller
{
    public function __construct(
        protected EmailDelivery $emailDelivery,
        protected ?N8nEmailSender $sender = null
    ) {
        $this->sender ??= app(N8nEmailSender::class);
    }

    public function index(Request $request): Response|JsonResponse|\Illuminate\View\View
    {
        $query = EmailMessage::query();

        $type = $request->query('type');
        if (is_string($type) && in_array($type, EmailMessage::VALID_TYPES, true)) {
            $query->where('type', $type);
        }

        $status = $request->query('status');
        if (is_string($status) && in_array($status, EmailMessage::VALID_STATUSES, true)) {
            $query->where('status', $status);
        }

        $search = $request->query('search') ?? $request->query('q');
        if (is_string($search) && trim($search) !== '') {
            $searchTerm = trim($search);
            $query->where(function ($q) use ($searchTerm): void {
                if (is_numeric($searchTerm)) {
                    $q->where('id', (int) $searchTerm);
                } else {
                    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchTerm);
                    $q->where('subject', 'like', "%{$escaped}%");
                }
            });
        }

        $messages = $query->latest('id')->paginate(20)->withQueryString();

        if ($request->wantsJson() || ! View::exists('admin.emails.index')) {
            return response()->json([
                'data' => $messages->items(),
                'current_page' => $messages->currentPage(),
                'total' => $messages->total(),
            ]);
        }

        return view('admin.emails.index', compact('messages'));
    }

    public function create(Request $request): Response|JsonResponse|\Illuminate\View\View
    {
        if ($request->wantsJson() || ! View::exists('admin.emails.create')) {
            return response()->json(['message' => 'Create email message form']);
        }

        return view('admin.emails.create');
    }

    public function store(SendEmailRequest $request): RedirectResponse|JsonResponse
    {
        $trustedAttachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if ($file->isValid()) {
                    $storedPath = $file->store('email-attachments', 'local');
                    $trustedAttachments[] = [
                        'path' => $storedPath,
                        'name' => $file->getClientOriginalName(),
                    ];
                }
            }
        }

        $notificationId = $this->emailDelivery->send([
            'recipient_user_id' => $request->validated('recipient_user_id'),
            'recipient_email' => (string) $request->validated('recipient_email'),
            'recipient_name' => (string) ($request->validated('recipient_name') ?? $request->validated('name') ?? ''),
            'subject' => (string) $request->validated('subject'),
            'body' => (string) $request->validated('body'),
            'type' => (string) $request->validated('type'),
            'action_url' => $request->validated('action_url'),
            'action_label' => $request->validated('action_label'),
            'attachments' => $trustedAttachments,
        ]);

        $createdMessage = EmailMessage::find($notificationId);
        $status = $createdMessage?->status ?? EmailMessage::STATUS_QUEUED;

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $status === EmailMessage::STATUS_SUBMITTED,
                'id' => $notificationId,
                'status' => $status,
                'message' => match ($status) {
                    EmailMessage::STATUS_SUBMITTED => 'Email berhasil dikirim ke n8n.',
                    EmailMessage::STATUS_UNKNOWN => 'Pengiriman ambigu: tidak ada respon pasti dari n8n/SMTP.',
                    EmailMessage::STATUS_FAILED => 'Pengiriman email gagal.',
                    default => 'Email diproses.',
                },
            ], 201);
        }

        $redirect = redirect()->route('admin.emails.show', $notificationId);
        if ($status === EmailMessage::STATUS_SUBMITTED) {
            return $redirect->with('success', 'Email berhasil dikirim ke n8n.');
        } elseif ($status === EmailMessage::STATUS_UNKNOWN) {
            return $redirect->with('warning', 'Pengiriman email dalam status ambigu. Periksa kembali.');
        } elseif ($status === EmailMessage::STATUS_FAILED) {
            return $redirect->with('error', 'Pengiriman email gagal: '.($createdMessage?->error_message ?? 'Terjadi kesalahan.'));
        }

        return $redirect->with('info', 'Email belum dikirim.');
    }

    public function show(Request $request, int|string $id): Response|JsonResponse|\Illuminate\View\View
    {
        $message = EmailMessage::findOrFail($id);

        if ($message->type === EmailMessage::TYPE_VERIFICATION) {
            $message->setAttribute('body', null);
            $message->setAttribute('action_url', null);
            $message->setAttribute('action_label', null);
        } else {
            $payload = $message->payload ?? [];
            $message->setAttribute('body', $payload['body'] ?? null);
            $message->setAttribute('action_url', $payload['action_url'] ?? null);
            $message->setAttribute('action_label', $payload['action_label'] ?? null);
        }

        if ($request->wantsJson() || ! View::exists('admin.emails.show')) {
            $data = [
                'id' => $message->id,
                'subject' => e($message->subject),
                'type' => $message->type,
                'status' => $message->status,
                'recipient' => [
                    'masked_email' => $message->getMaskedRecipient(),
                    'name' => e($message->getRecipientName()),
                ],
                'error_message' => $message->error_message,
                'submitted_at' => $message->submitted_at?->toISOString(),
                'created_at' => $message->created_at?->toISOString(),
            ];

            if ($message->type !== EmailMessage::TYPE_VERIFICATION) {
                $data['body'] = $message->body;
                $data['action_url'] = $message->action_url;
                $data['action_label'] = $message->action_label;
            }

            return response()->json($data);
        }

        return view('admin.emails.show', compact('message'));
    }

    public function retry(RetryEmailRequest $request, int|string $id): RedirectResponse|JsonResponse
    {
        $message = EmailMessage::findOrFail($id);

        if ($message->type === EmailMessage::TYPE_VERIFICATION) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Pesan verifikasi tidak dapat dikirim ulang secara manual demi keamanan.',
                ], 422);
            }

            return redirect()->back()->with('error', 'Pesan verifikasi tidak dapat dikirim ulang secara manual demi keamanan.');
        }

        if ($message->status === EmailMessage::STATUS_PROCESSING) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Email is currently being processed.'], 409);
            }

            return redirect()->back()->with('error', 'Pesan sedang dalam proses pengiriman.');
        }

        $warning = null;
        if (in_array($message->status, [EmailMessage::STATUS_UNKNOWN, EmailMessage::STATUS_SUBMITTED], true)) {
            abort_unless($request->boolean('confirm_resend'), 422, 'Konfirmasi kirim ulang diperlukan.');
            $warning = 'Perhatian: Email mungkin sudah diterima sebelumnya. Kirim ulang manual dapat menghasilkan email ganda.';
        }

        $claimed = EmailMessage::query()->whereKey($message->id)
            ->where('status', $message->status)
            ->whereIn('status', [EmailMessage::STATUS_QUEUED, EmailMessage::STATUS_FAILED, EmailMessage::STATUS_UNKNOWN, EmailMessage::STATUS_SUBMITTED])
            ->update([
                'status' => EmailMessage::STATUS_QUEUED,
                'error_message' => null,
                'submitted_at' => null,
                'failed_at' => null,
            ]);
        abort_unless($claimed || $message->status === EmailMessage::STATUS_QUEUED, 409, 'Pesan sedang diproses atau status telah berubah. Silakan muat ulang halaman.');

        $this->sender->send($message->id);
        $fresh = $message->fresh();
        $status = $fresh?->status ?? EmailMessage::STATUS_UNKNOWN;

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $status === EmailMessage::STATUS_SUBMITTED,
                'status' => $status,
                'message' => match ($status) {
                    EmailMessage::STATUS_SUBMITTED => 'Email berhasil dikirim ke n8n.',
                    EmailMessage::STATUS_UNKNOWN => 'Pengiriman ambigu: tidak ada respon pasti dari n8n/SMTP.',
                    default => 'Pengiriman email gagal: '.($fresh?->error_message ?? 'Terjadi kesalahan.'),
                },
                'warning' => $warning,
            ]);
        }

        $redirect = redirect()->route('admin.emails.show', $message->id);
        if ($status === EmailMessage::STATUS_SUBMITTED) {
            $redirect->with('success', 'Email berhasil dikirim ke n8n.');
        } elseif ($status === EmailMessage::STATUS_UNKNOWN) {
            $redirect->with('warning', 'Pengiriman email dalam status ambigu. Periksa kembali sebelum kirim ulang.');
        } else {
            $redirect->with('error', 'Pengiriman email gagal: '.($fresh?->error_message ?? 'Terjadi kesalahan.'));
        }

        if ($warning) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function preview(Request $request): Response|JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(EmailMessage::VALID_TYPES)],
            'action_url' => [
                'nullable',
                'string',
                'max:2048',
                'regex:/^https?:\/\//i',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value)) {
                        $lowered = strtolower(trim($value));
                        if (
                            str_starts_with($lowered, 'javascript:')
                            || str_starts_with($lowered, 'data:')
                            || str_starts_with($lowered, 'vbscript:')
                            || ! filter_var($value, FILTER_VALIDATE_URL)
                        ) {
                            $fail('Action URL contains unsafe protocol or invalid URL.');
                        }
                    }
                },
            ],
            'action_label' => ['nullable', 'string', 'max:100'],
        ]);

        $recipientName = (string) ($validated['recipient_name'] ?? $validated['name'] ?? 'Recipient');
        $title = (string) $validated['subject'];
        $body = (string) $validated['body'];
        $type = (string) $validated['type'];
        $actionUrl = $validated['action_url'] ?? null;
        $actionLabel = $validated['action_label'] ?? null;

        $viewData = [
            'type' => $type,
            'title' => $title,
            'subject' => $title,
            'body' => $body,
            'recipientName' => $recipientName,
            'recipient_name' => $recipientName,
            'actionURL' => $actionUrl,
            'action_url' => $actionUrl,
            'actionLabel' => $actionLabel,
            'action_label' => $actionLabel,
        ];

        $html = View::exists('emails.actstation')
            ? View::make('emails.actstation', $viewData)->render()
            : '<!DOCTYPE html><html><body><h2>'.e($title).'</h2><p>Halo '.e($recipientName).',</p><p>'.nl2br(e($body)).'</p>'.($actionUrl ? '<p><a href="'.e((string) $actionUrl).'">'.e((string) ($actionLabel ?? 'Klik di sini')).'</a></p>' : '').'</body></html>';

        if ($request->wantsJson()) {
            return response()->json(['html' => $html]);
        }

        return response($html, 200, ['Content-Type' => 'text/html']);
    }
}

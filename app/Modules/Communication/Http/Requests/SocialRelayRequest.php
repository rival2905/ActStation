<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Requests;

use App\Models\AgentOfficePrincipal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SocialRelayRequest extends FormRequest
{
    public function action(): string
    {
        $method = $this->route()?->getActionMethod();
        if (is_string($method) && in_array($method, ['publish', 'pull', 'ack'], true)) {
            return $method;
        }

        $relayAction = $this->route()?->parameter('relay_action') ?? $this->route()?->defaults['relay_action'] ?? null;
        if (is_string($relayAction) && in_array($relayAction, ['publish', 'pull', 'ack'], true)) {
            return $relayAction;
        }

        $path = trim($this->getPathInfo(), '/');
        $segments = explode('/', $path);
        $last = end($segments);
        if (in_array($last, ['publish', 'pull', 'ack'], true)) {
            return $last;
        }

        return '';
    }

    public function authorize(): bool
    {
        $user = $this->user();
        if (! ($user instanceof AgentOfficePrincipal)) {
            return false;
        }

        $token = $user->currentAccessToken();
        if (! $token) {
            return false;
        }

        $abilities = (array) ($token->abilities ?? []);
        if (in_array('*', $abilities, true)) {
            return false;
        }

        $relayAbilities = array_values(array_intersect(
            ['agent:social-collector', 'agent:social-office', 'agent:social-sender'],
            $abilities
        ));
        if (count($relayAbilities) !== 1) {
            return false;
        }

        $action = $this->action();
        $channel = $this->input('channel');

        $isCollector = $user->tokenCan('agent:social-collector');
        $isOffice = $user->tokenCan('agent:social-office');
        $isSender = $user->tokenCan('agent:social-sender');

        if (is_string($channel) && in_array($channel, ['comments', 'receipts', 'dispatch'], true)) {
            return match ([$action, $channel]) {
                ['publish', 'comments'] => $isCollector,
                ['publish', 'dispatch'] => $isOffice,
                ['publish', 'receipts'] => $isSender,
                ['pull', 'comments'], ['pull', 'receipts'] => $isOffice,
                ['pull', 'dispatch'] => $isSender,
                ['ack', 'comments'], ['ack', 'receipts'] => $isOffice,
                ['ack', 'dispatch'] => $isSender,
                default => false,
            };
        }

        return match ($action) {
            'publish' => $isCollector || $isOffice || $isSender,
            'pull', 'ack' => $isOffice || $isSender,
            default => false,
        };
    }

    public function rules(): array
    {
        $accounts = (array) config('agent_office.social_relay_accounts', []);
        $action = $this->action();
        $channel = $this->input('channel');

        $common = [
            'channel' => ['required', 'string', 'in:comments,receipts,dispatch'],
            'account_ref' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/D', Rule::in($accounts)],
        ];

        if ($action === 'pull') {
            return $common;
        }

        if ($action === 'ack') {
            return $common + [
                'delivery_id' => ['required', 'string', 'uuid'],
                'claim_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            ];
        }

        $publishBase = $common + [
            'delivery_id' => ['required', 'string', 'uuid'],
            'payload' => ['required', 'array'],
        ];

        if ($channel === 'comments') {
            return $publishBase + [
                'expires_at' => ['prohibited'],
                'payload' => ['required', 'array:account_ref,platform,media,comments,page'],
                'payload.account_ref' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/D', Rule::in($accounts)],
                'payload.platform' => ['required', 'in:instagram'],
                'payload.media' => ['required', 'array:id,permalink,published_at'],
                'payload.media.id' => ['required', 'string', 'max:80', 'regex:/^[0-9]+$/D'],
                'payload.media.permalink' => ['required', 'string', 'max:300', 'regex:~^https://www\.instagram\.com/(?:p|reel)/[A-Za-z0-9_-]+/$~D'],
                'payload.media.published_at' => ['required', 'date', 'before_or_equal:now', 'regex:/(?:Z|[+-][0-9]{2}:[0-9]{2})$/D'],
                'payload.comments' => ['present', 'array', 'list', 'max:200'],
                'payload.comments.*' => ['required', 'array:id,text,timestamp,username,sender_id'],
                'payload.comments.*.id' => ['required', 'string', 'max:80', 'distinct:strict', 'regex:/^[0-9]+$/D'],
                'payload.comments.*.text' => ['present', 'string', 'max:10000'],
                'payload.comments.*.timestamp' => ['required', 'date', 'before_or_equal:now', 'regex:/(?:Z|[+-][0-9]{2}:[0-9]{2})$/D'],
                'payload.comments.*.username' => ['sometimes', 'nullable', 'string', 'max:80'],
                'payload.comments.*.sender_id' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[0-9]+$/D'],
                'payload.page' => ['required', 'integer', 'min:1', 'max:10000'],
            ];
        }

        if ($channel === 'receipts') {
            return $publishBase + [
                'expires_at' => ['prohibited'],
                'payload' => ['required', 'array:account_ref,item_id,lease_token,reply,dm,error_code'],
                'payload.account_ref' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/D', Rule::in($accounts)],
                'payload.item_id' => ['required', 'string', 'uuid'],
                'payload.lease_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
                'payload.reply' => ['required', 'array:state,id'],
                'payload.reply.state' => ['required', 'in:sent,failed,unknown,skipped'],
                'payload.reply.id' => ['required_if:payload.reply.state,sent', 'prohibited_unless:payload.reply.state,sent', 'string', 'max:80', 'regex:/^[0-9]+$/D'],
                'payload.dm' => ['required', 'array:state,receipt_present'],
                'payload.dm.state' => ['required', 'in:sent,failed,unknown,skipped'],
                'payload.dm.receipt_present' => ['required', 'boolean'],
                'payload.error_code' => ['sometimes', 'nullable', 'in:meta_rejected,meta_rate_limited,transport_timeout,readback_mismatch,transport_failed'],
            ];
        }

        if ($channel === 'dispatch') {
            // Scheduled dispatch: Office hands over an approved reply with its send time; no expiry while waiting.
            return $publishBase + [
                'expires_at' => ['prohibited'],
                'not_before' => ['required', 'string', 'date', 'regex:/(?:Z|[+-][0-9]{2}:[0-9]{2})$/D'],
                'payload' => ['required', 'array:item_id,account_ref,platform,account_id,comment_id,media_id,lane,reply_text,dm_text,lease_token'],
                'payload.item_id' => ['required', 'string', 'uuid'],
                'payload.account_ref' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/D', Rule::in($accounts)],
                'payload.platform' => ['required', 'in:instagram'],
                'payload.account_id' => ['required', 'string', 'max:80', 'regex:/^[0-9]+$/D'],
                'payload.comment_id' => ['required', 'string', 'max:80', 'regex:/^[0-9]+$/D'],
                'payload.media_id' => ['required', 'string', 'max:80', 'regex:/^[0-9]+$/D'],
                'payload.lane' => ['required', 'in:reply_dm,reply_only'],
                'payload.reply_text' => ['required', 'string', 'max:8000'],
                'payload.dm_text' => ['nullable', 'string', 'max:1000'],
                'payload.lease_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            ];
        }

        return $publishBase;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $action = $this->action();
            $channel = $this->input('channel');

            $allowedRoot = match ($action) {
                'pull' => ['channel', 'account_ref'],
                'ack' => ['channel', 'account_ref', 'delivery_id', 'claim_token'],
                'publish' => $channel === 'dispatch'
                    ? ['channel', 'account_ref', 'delivery_id', 'payload', 'not_before']
                    : ['channel', 'account_ref', 'delivery_id', 'payload'],
                default => [],
            };

            if (array_diff(array_keys($this->all()), $allowedRoot)) {
                $validator->errors()->add('payload', 'Unknown root fields.');
            }

            if ($action === 'publish') {
                $payload = $this->input('payload');
                if (! is_array($payload)) {
                    return;
                }

                $allowedPayload = match ($channel) {
                    'comments' => ['account_ref', 'platform', 'media', 'comments', 'page'],
                    'receipts' => ['account_ref', 'item_id', 'lease_token', 'reply', 'dm', 'error_code'],
                    'dispatch' => ['item_id', 'account_ref', 'platform', 'account_id', 'comment_id', 'media_id', 'lane', 'reply_text', 'dm_text', 'lease_token'],
                    default => [],
                };

                if (! empty($allowedPayload) && array_diff(array_keys($payload), $allowedPayload)) {
                    $validator->errors()->add('payload', 'Unknown payload fields.');
                }

                if (isset($payload['account_ref']) && $payload['account_ref'] !== $this->input('account_ref')) {
                    $validator->errors()->add('payload.account_ref', 'Inner account_ref must match outer account_ref.');
                }

                if ($channel === 'comments') {
                    if (is_array($media = $this->input('payload.media'))) {
                        if (array_diff(array_keys($media), ['id', 'permalink', 'published_at'])) {
                            $validator->errors()->add('payload.media', 'Unknown media fields.');
                        }
                    }
                    if (is_array($comments = $this->input('payload.comments'))) {
                        foreach ($comments as $idx => $comment) {
                            if (is_array($comment) && array_diff(array_keys($comment), ['id', 'text', 'timestamp', 'username', 'sender_id'])) {
                                $validator->errors()->add("payload.comments.{$idx}", 'Unknown comment fields.');
                            }
                        }
                    }
                } elseif ($channel === 'receipts') {
                    if (is_array($reply = $this->input('payload.reply'))) {
                        if (array_diff(array_keys($reply), ['state', 'id'])) {
                            $validator->errors()->add('payload.reply', 'Unknown reply fields.');
                        }
                    }
                    if (is_array($dm = $this->input('payload.dm'))) {
                        if (array_diff(array_keys($dm), ['state', 'receipt_present'])) {
                            $validator->errors()->add('payload.dm', 'Unknown dm fields.');
                        }
                    }

                    $dmState = $this->input('payload.dm.state');
                    $dmReceipt = $this->input('payload.dm.receipt_present');
                    $replyState = $this->input('payload.reply.state');

                    if ($dmState === 'sent') {
                        if ($dmReceipt !== true) {
                            $validator->errors()->add('payload.dm.receipt_present', 'Receipt required.');
                        }
                        if ($replyState !== 'sent') {
                            $validator->errors()->add('payload.reply.state', 'Reply must be sent when DM is sent.');
                        }
                    }
                } elseif ($channel === 'dispatch') {
                    $lane = $this->input('payload.lane');
                    $dmText = $this->input('payload.dm_text');
                    $replyText = $this->input('payload.reply_text');

                    if ($lane === 'reply_only' && $dmText !== null) {
                        $validator->errors()->add('payload.dm_text', 'DM text must be null for reply_only lane.');
                    }

                    if ($lane === 'reply_dm' && (! is_string($dmText) || trim($dmText) === '')) {
                        $validator->errors()->add('payload.dm_text', 'DM text is required for reply_dm lane.');
                    }

                    if (is_string($replyText)) {
                        if (trim($replyText) === '') {
                            $validator->errors()->add('payload.reply_text', 'Reply text must not be empty.');
                        }
                        if (strlen($replyText) > 8000) {
                            $validator->errors()->add('payload.reply_text', 'Reply text exceeds 8000 bytes.');
                        }
                    }

                    if (is_string($dmText) && strlen($dmText) > 1000) {
                        $validator->errors()->add('payload.dm_text', 'DM text exceeds 1000 bytes.');
                    }

                    // ponytail: schedule window 24 h either side (a retried handover may carry a past slot); widen only with a cancel action.
                    $val = $this->input('not_before');
                    if (is_string($val) && preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/D', $val)) {
                        try {
                            if (abs(Carbon::parse($val)->diffInSeconds(Carbon::now())) > 86400) {
                                $validator->errors()->add('not_before', 'Schedule must be within 24 hours.');
                            }
                        } catch (\Throwable) {
                            $validator->errors()->add('not_before', 'Invalid schedule timestamp.');
                        }
                    }
                }
            }
        }];
    }
}

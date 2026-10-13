<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            if (method_exists($model, 'recordActivity')) {
                $model->recordActivity('created');
            }
        });

        static::updated(function (Model $model): void {
            if (method_exists($model, 'recordActivity')) {
                $model->recordActivity('updated');
            }
        });

        static::deleted(function (Model $model): void {
            if (method_exists($model, 'recordActivity')) {
                $model->recordActivity('deleted');
            }
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model): void {
                if (method_exists($model, 'recordActivity')) {
                    $model->recordActivity('restored');
                }
            });
        }
    }

    public function recordActivity(string $event): void
    {
        try {
            $oldValues = null;
            $newValues = null;

            $group = is_string($this->getAttribute('group')) ? $this->getAttribute('group') : null;
            $oldGroup = is_string($this->getOriginal('group')) ? $this->getOriginal('group') : $group;

            if ($event === 'created') {
                $newValues = ActivityLog::redact($this->getAttributes(), $group);
            } elseif ($event === 'updated') {
                $changes = $this->getChanges();
                unset($changes['updated_at']);

                $old = [];
                $new = [];
                foreach ($changes as $key => $newValue) {
                    if ($this->originalIsEquivalent($key)) {
                        continue;
                    }
                    $old[$key] = $this->getOriginal($key);
                    $new[$key] = $newValue;
                }

                if (empty($new)) {
                    return;
                }

                $oldValues = ActivityLog::redact($old, $oldGroup);
                $newValues = ActivityLog::redact($new, $group);
            } elseif ($event === 'deleted') {
                $attributes = $this->getOriginal() ?: $this->getAttributes();
                $oldValues = ActivityLog::redact($attributes, $oldGroup);
            } elseif ($event === 'restored') {
                $newValues = ['restored_at' => now()->toIso8601String()];
            }

            $user = auth()->user();
            $roleContext = null;
            if ($user && method_exists($user, 'roles')) {
                $roleContext = $user->roles->first()?->name;
            }

            $ipAddress = request()?->ip();
            $userAgent = request() ? substr((string) request()->userAgent(), 0, 255) : null;

            ActivityLog::create([
                'user_id' => $user?->getAuthIdentifier(),
                'event' => $event,
                'auditable_type' => $this->getMorphClass(),
                'auditable_id' => $this->getKey(),
                'role_context' => $roleContext,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write activity log: '.$e->getMessage(), [
                'exception' => $e,
                'event' => $event,
                'auditable_type' => static::class,
                'auditable_id' => $this->getKey(),
            ]);
        }
    }
}

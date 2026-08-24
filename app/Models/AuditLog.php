<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single entry in the platform audit trail. Rows are denormalised
 * (`actor_name`, `target`) so they stay legible independent of related records.
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'actor_name', 'action', 'target', 'ip', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Write one audit row. The whole point of the trail is that recording it
     * must never break the action being recorded, so every failure (missing
     * table on a fresh install, a bad column) is swallowed. Actor + IP are
     * resolved from the current request when not passed.
     *
     *   AuditLog::record('user.login', 'admin@x.com');
     *   AuditLog::record('settings.mail.update', 'mail', ['host' => $host]);
     */
    public static function record(string $action, ?string $target = null, array $meta = [], ?string $actorName = null): void
    {
        try {
            $user = auth()->user();

            static::create([
                'user_id'    => $user?->getAuthIdentifier(),
                'actor_name' => $actorName ?: ($user?->name ?: $user?->email ?: 'system'),
                'action'     => $action,
                'target'     => $target,
                'ip'         => request()?->ip(),
                'meta'       => $meta ?: null,
            ]);
        } catch (\Throwable $e) {
            // Never let auditing break the request it is auditing.
        }
    }
}

<?php

namespace App\Services;

use App\Models\AuditLog;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * One place to write audit_logs rows (who, what, which record, old/new values, IP).
 * Never pass secrets (passwords, tokens) in $old / $new.
 */
class AuditLogger
{
    public static function log(string $action, Model $model, ?array $old = null, ?array $new = null): void
    {
        AuditLog::create([
            'user_id' => request()->user()?->id,
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $old === null ? null : self::normalize($old),
            'new_values' => $new === null ? null : self::normalize($new),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    /** Dates become ISO strings and enums become their value, so JSON stays readable. */
    public static function normalize(array $values): array
    {
        return array_map(function ($value) {
            return match (true) {
                $value instanceof DateTimeInterface => $value->format('c'),
                $value instanceof BackedEnum => $value->value,
                is_array($value) => self::normalize($value),
                default => $value,
            };
        }, $values);
    }
}

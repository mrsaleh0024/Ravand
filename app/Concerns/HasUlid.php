<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Assigns a ULID (26-char, lexicographically sortable) string primary key
 * to the model, as required by the Ravand data standard (ADR-004).
 *
 * The ULID is generated on the server at creation time and is NOT considered
 * an authorization mechanism by itself (see spec section 4.1).
 */
trait HasUlid
{
    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    protected static function bootHasUlid(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->getKey()) {
                /** @phpstan-ignore assign.propertyType (ULID string intentionally assigned to generic Model key) */
                $model->setAttribute($model->getKeyName(), (string) Str::ulid());
            }
        });
    }
}

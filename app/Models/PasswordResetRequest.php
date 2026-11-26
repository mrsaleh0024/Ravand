<?php

namespace App\Models;

use App\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * A password-reset request filed from the login screen (ADR-021).
 *
 * Lifecycle: pending → approved | rejected. Approval is done by the owner or
 * a manager inside the panel by issuing a temporary password; the requester
 * is then contacted by phone (no messages are ever sent to end users).
 *
 * @property string $id
 * @property string|null $user_id
 * @property string $phone
 * @property string $status
 * @property string|null $requested_ip
 * @property string|null $handled_by
 * @property Carbon|null $handled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'phone', 'status', 'requested_ip', 'handled_by', 'handled_at'])]
class PasswordResetRequest extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\PasswordResetRequestFactory> */
    use HasFactory, HasUlid;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}

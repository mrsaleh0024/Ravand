<?php

namespace Database\Factories;

use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PasswordResetRequest>
 */
class PasswordResetRequestFactory extends Factory
{
    protected static int $sequence = 0;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'phone' => '0912'.str_pad((string) (1000000 + (++static::$sequence)), 7, '0', STR_PAD_LEFT),
            'status' => PasswordResetRequest::STATUS_PENDING,
            'requested_ip' => '127.0.0.1',
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'phone' => $user->phone,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PasswordResetRequest::STATUS_APPROVED,
            'handled_at' => now(),
        ]);
    }
}

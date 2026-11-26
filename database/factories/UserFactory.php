<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    protected static int $phoneSequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => static::nextPhone(),
            'status' => 'active',
            'password' => static::$password ??= Hash::make('password'),
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /** Deterministic unique Iranian mobile numbers across factory calls. */
    public static function nextPhone(): string
    {
        return '0912'.str_pad((string) (3000000 + (++static::$phoneSequence)), 7, '0', STR_PAD_LEFT);
    }

    /**
     * Indicate that the account is suspended (server-side lockout).
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    /**
     * Indicate that the account must choose a new password on next login
     * (used after a temporary password was issued by the panel).
     */
    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'must_change_password' => true,
        ]);
    }

    /**
     * Indicate that the owner account has a Bale chat id for OTP 2FA.
     */
    public function withBaleChat(): static
    {
        return $this->state(fn (array $attributes) => [
            'bale_chat_id' => '123456789',
        ]);
    }
}

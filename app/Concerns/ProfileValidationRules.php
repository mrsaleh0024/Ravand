<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles (ADR-019).
     *
     * @return array<string, array<int, Unique|ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?string $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'phone' => $this->phoneRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:80'];
    }

    /**
     * Iranian mobile number rules: normalized to 09xxxxxxxxx before the
     * unique check so every user shape maps to the stored value.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function phoneRules(?string $userId = null): array
    {
        return [
            'required',
            'string',
            'max:20',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (Phone::normalize(is_string($value) ? $value : null) === null) {
                    $fail(__('validation.phone_iranian'));
                }
            },
            $userId === null
                ? Rule::unique(User::class, 'phone')
                : Rule::unique(User::class, 'phone')->ignore($userId),
        ];
    }
}

<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tenant settings update (phase 01). Only the fields below are changeable;
 * `slug` is immutable because it identifies the store in URLs and future
 * exports.
 */
class TenantSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('tenant.settings.update');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'default_currency' => ['required', 'string', Rule::in(['IRR', 'IRT'])],
            'timezone' => ['required', 'string', Rule::in(config('ravand.allowed_timezones'))],
            'locale' => ['required', 'string', Rule::in(config('ravand.allowed_locales'))],
            'calendar' => ['required', 'string', Rule::in(config('ravand.allowed_calendars'))],
        ];
    }
}

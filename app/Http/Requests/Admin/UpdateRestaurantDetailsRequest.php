<?php

namespace App\Http\Requests\Admin;

use App\Enums\Capability;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestaurantDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::MANAGE_RESTAURANT_CONFIG->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $locales = array_keys((array) config('qresto.locales', []));

        return [
            'name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'cuisine' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'currency' => ['required', 'string', Rule::in((array) config('qresto.currencies', []))],
            // Applies to carts and new orders only; a placed order keeps the
            // rate it was placed with (orders.service_pct).
            'service_charge_pct' => ['required', 'numeric', 'min:0', 'max:30', 'decimal:0,2'],
            'supported_locales' => ['required', 'array', 'min:1'],
            'supported_locales.*' => ['string', 'distinct', Rule::in($locales)],
            'default_locale' => ['required', 'string', Rule::in($locales), Rule::in((array) $this->input('supported_locales', []))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency.in' => 'Pick a currency from the list.',
            'timezone.in' => 'Pick a timezone from the list.',
            'supported_locales.*.in' => 'That language is not available yet.',
            'default_locale.in' => 'The default language must be one of the languages you offer.',
            'service_charge_pct.max' => 'A service charge above 30% is almost certainly a typo.',
        ];
    }
}

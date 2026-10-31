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
            'currency.in' => __('messages.restaurant.currency'),
            'timezone.in' => __('messages.restaurant.timezone'),
            'supported_locales.*.in' => __('messages.restaurant.locale_unavailable'),
            'default_locale.in' => __('messages.restaurant.default_locale'),
            'service_charge_pct.max' => __('messages.restaurant.service_charge_max'),
        ];
    }
}

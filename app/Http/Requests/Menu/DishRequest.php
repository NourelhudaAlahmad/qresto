<?php

namespace App\Http\Requests\Menu;

use App\Enums\Capability;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Everything in the editor except availability, which has its own
 * immediate-save endpoint (#31).
 */
class DishRequest extends FormRequest
{
    private const MONEY = 'regex:/^-?\d{1,5}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        return $this->user()?->can(Capability::EDIT_MENU_PRICES->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        $minWidth = (int) config('qresto.menu.photo_min_width', 1200);

        return [
            'menu_category_id' => [
                'required', 'integer',
                Rule::exists('menu_categories', 'id')->where('restaurant_id', $user->restaurant_id),
            ],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:600'],
            'price' => ['required', 'string', 'regex:/^\d{1,5}(\.\d{1,2})?$/'],
            'prep_minutes' => ['nullable', 'integer', 'between:0,180'],
            'chef_flag' => ['sometimes', 'boolean'],
            'dietary_tags' => ['sometimes', 'array', 'max:6'],
            'dietary_tags.*' => ['string', 'max:24'],

            // 4:3 and at least 1200px wide; kept as the original, cropped to
            // a square for list rows.
            'photo' => [
                'nullable', 'image', 'mimes:jpeg,png,webp', 'max:8192',
                "dimensions:min_width={$minWidth},ratio=4/3",
            ],

            'allergens' => ['sometimes', 'array', 'max:20'],
            'allergens.*.id' => [
                'nullable', 'integer',
                Rule::exists('allergens', 'id')->where('restaurant_id', $user->restaurant_id),
            ],
            'allergens.*.name' => ['required_without:allergens.*.id', 'nullable', 'string', 'max:40'],
            'allergens.*.may_contain' => ['sometimes', 'boolean'],

            'variants' => ['sometimes', 'array', 'max:10'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.label' => ['required', 'string', 'max:40'],
            'variants.*.price_delta' => ['required', 'string', self::MONEY],
            'variants.*.is_default' => ['sometimes', 'boolean'],

            'addons' => ['sometimes', 'array', 'max:20'],
            'addons.*.id' => ['nullable', 'integer'],
            'addons.*.label' => ['required', 'string', 'max:40'],
            'addons.*.price_delta' => ['required', 'string', self::MONEY],
            'addons.*.is_available' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.dimensions' => 'Use a 4:3 photo at least 1200px wide.',
            'photo.mimes' => 'Use a JPEG, PNG or WebP photo.',
            'price.regex' => 'Enter a price like 18 or 18.50.',
        ];
    }
}

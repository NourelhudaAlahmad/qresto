<?php

namespace App\Http\Requests\Menu;

use App\Enums\Capability;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCategoriesRequest extends FormRequest
{
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

        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'integer',
                'distinct',
                Rule::exists('menu_categories', 'id')->where('restaurant_id', $user->restaurant_id),
            ],
        ];
    }
}

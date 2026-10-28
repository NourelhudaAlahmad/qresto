<?php

namespace App\Http\Requests\Admin;

use App\Enums\Capability;
use App\Models\RestaurantTable;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TableRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->user();

        /** @var RestaurantTable|null $table */
        $table = $this->route('table');

        return [
            'number' => [
                'required', 'string', 'max:10', 'regex:/^[\pL\pN][\pL\pN \-]*$/u',
                // Unique among the restaurant's active tables. A retired
                // table with the same number is brought back instead.
                Rule::unique('tables', 'number')
                    ->where('restaurant_id', $user->restaurant_id)
                    ->whereNull('retired_at')
                    ->ignore($table?->id),
                // The database keeps one row per number, retired or not, so
                // renaming onto a retired number would hit the unique index.
                function (string $attribute, mixed $value, Closure $fail) use ($user, $table): void {
                    if ($table === null) {
                        return; // adding a retired number brings that table back
                    }

                    $retired = RestaurantTable::withoutGlobalScope(RestaurantTable::ACTIVE_SCOPE)
                        ->where('restaurant_id', $user->restaurant_id)
                        ->where('number', trim((string) $value))
                        ->whereNotNull('retired_at')
                        ->exists();

                    if ($retired) {
                        $fail('A retired table uses that number. Add it again from "New table" to bring it back.');
                    }
                },
            ],
            'seats' => ['required', 'integer', 'between:1,30'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number.unique' => 'There is already a table with that number.',
            'number.regex' => 'Use letters, numbers, spaces or dashes.',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Enums\Capability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateHoursRequest extends FormRequest
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
        return [
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'hours.*.closed' => ['required', 'boolean'],
            'hours.*.opens_at' => ['nullable', 'required_if:hours.*.closed,false', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'required_if:hours.*.closed,false', 'date_format:H:i'],
        ];
    }

    /**
     * A day must close after it opens. "00:00" means midnight, the end of
     * the day, as the seed's Friday and Saturday (12:00 – 00:00) do.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('hours', []) as $index => $day) {
                if (! is_array($day) || ($day['closed'] ?? false)) {
                    continue;
                }

                $opens = (string) ($day['opens_at'] ?? '');
                $closes = (string) ($day['closes_at'] ?? '');

                if ($opens === '' || $closes === '' || $closes === '00:00') {
                    continue;
                }

                if ($closes <= $opens) {
                    $validator->errors()->add(
                        "hours.{$index}.closes_at",
                        'Closing time must be after opening time.',
                    );
                }
            }
        }];
    }

    /**
     * @return list<array{day_of_week: int, opens_at: string|null, closes_at: string|null}>
     */
    public function days(): array
    {
        return array_values(array_map(fn (array $day): array => [
            'day_of_week' => (int) $day['day_of_week'],
            'opens_at' => ($day['closed'] ?? false) ? null : $day['opens_at'].':00',
            'closes_at' => ($day['closed'] ?? false) ? null : $day['closes_at'].':00',
        ], (array) $this->validated('hours')));
    }
}

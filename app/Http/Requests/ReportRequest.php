<?php

namespace App\Http\Requests;

use App\Enums\Capability;
use App\Services\Reports\SalesReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::FINANCIAL_REPORTS->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string', Rule::in(SalesReport::PRESETS)],
            'start' => ['required_if:preset,custom', 'nullable', 'date_format:Y-m-d'],
            'end' => ['required_if:preset,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }

    public function preset(): string
    {
        return (string) ($this->validated('preset') ?? 'month');
    }
}

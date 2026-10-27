<?php

namespace App\Http\Requests\Admin;

use App\Enums\Capability;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::MANAGE_RESTAURANT_CONFIG->value)
            ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::enum(Role::class)],
            'capability' => ['required', 'string', Rule::enum(Capability::class)],
            'granted' => ['required', 'boolean'],
        ];
    }

    public function role(): Role
    {
        return Role::from((string) $this->validated('role'));
    }

    public function capability(): Capability
    {
        return Capability::from((string) $this->validated('capability'));
    }

    public function granted(): bool
    {
        return (bool) $this->validated('granted');
    }
}

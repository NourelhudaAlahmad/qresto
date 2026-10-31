<?php

namespace App\Http\Requests\Staff;

use App\Enums\Capability;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared rules for creating and editing a staff member from the drawer.
 *
 * The role whitelist lives here, not in the UI: a manager can create a
 * waiter, runner, cashier or kitchen account, never a manager or an admin.
 */
abstract class StaffMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::MANAGE_WAITER_ACCOUNTS->value)
            ?? false;
    }

    /**
     * @return list<string>
     */
    public static function manageableRoles(): array
    {
        /** @var list<string> $roles */
        $roles = config('qresto.staff.manageable_roles', []);

        return $roles;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedRules(): array
    {
        /** @var User $actor */
        $actor = $this->user();

        return [
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', Rule::in(self::manageableRoles())],
            'tables' => ['present', 'array', 'max:40'],
            'tables.*' => [
                'string',
                'distinct',
                Rule::exists('tables', 'number')
                    ->where('restaurant_id', $actor->restaurant_id)
                    ->whereNull('retired_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('messages.staff.name_required'),
            'email.required' => __('messages.staff.email_required'),
            'email.email' => __('messages.staff.email_format'),
            'email.unique' => __('messages.staff.email_taken'),
            'role.in' => __('messages.staff.role_allowed'),
            'tables.*.exists' => __('messages.staff.table_not_on_floor'),
        ];
    }
}

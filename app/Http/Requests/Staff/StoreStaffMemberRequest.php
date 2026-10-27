<?php

namespace App\Http\Requests\Staff;

class StoreStaffMemberRequest extends StaffMemberRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->sharedRules(),
            'email' => [
                'required',
                'string',
                'email',
                'max:254',
                'unique:users,email',
            ],
            'send_invite' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, email: string, role: string, tables: list<string>, send_invite: bool}
     */
    public function staffData(): array
    {
        return [
            'name' => trim((string) $this->validated('name')),
            'email' => (string) $this->validated('email'),
            'role' => (string) $this->validated('role'),
            'tables' => array_values(array_map('strval', $this->validated('tables'))),
            'send_invite' => (bool) $this->validated('send_invite'),
        ];
    }
}

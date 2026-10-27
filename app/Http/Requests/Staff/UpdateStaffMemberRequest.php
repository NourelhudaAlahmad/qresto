<?php

namespace App\Http\Requests\Staff;

class UpdateStaffMemberRequest extends StaffMemberRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->sharedRules();
    }

    /**
     * @return array{name: string, role: string, tables: list<string>}
     */
    public function staffData(): array
    {
        return [
            'name' => trim((string) $this->validated('name')),
            'role' => (string) $this->validated('role'),
            'tables' => array_values(array_map('strval', $this->validated('tables'))),
        ];
    }
}

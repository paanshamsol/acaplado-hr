<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-staff');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'employee_id' => ['required', 'string', 'max:50', Rule::unique('users', 'employee_id')->whereNull('deleted_at')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'ic_number' => [
                'nullable',
                'string',
                'max:14',
                Rule::unique('users', 'ic_number')->whereNull('deleted_at'),
                'regex:/^\d{6}-\d{2}-\d{3}\d$/',
            ],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', 'in:staff,contract,part-time,hr,admin'],
            'join_date' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
            'profile_photo' => ['nullable', 'image', 'max:4096'],
            'face_descriptor' => ['nullable', 'string'],
        ];
    }
}

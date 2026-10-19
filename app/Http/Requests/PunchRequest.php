<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
            'descriptor' => ['required', 'array', 'min:64'],
            'descriptor.*' => ['numeric'],
            'image' => ['nullable', 'string'],
        ];
    }
}

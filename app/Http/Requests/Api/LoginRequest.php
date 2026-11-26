<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'device_name' => ['required', 'string', 'max:255'],
            'two_factor_code' => ['nullable', 'string', 'max:255'],
        ];
    }
}

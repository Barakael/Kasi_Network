<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            /*
             * Names the issued token so an operator can see and revoke
             * "Neema's phone" from the console.
             */
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}

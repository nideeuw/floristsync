<?php

namespace App\Http\Requests\Auth;

use App\Models\Role;
use App\Services\AreaAuthService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     * This door (/login) is for members only.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        app(AreaAuthService::class)->attempt($this, [Role::MEMBER]);
    }
}

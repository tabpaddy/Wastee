<?php

namespace App\Http\Requests\Onboarding;

use App\Services\Onboarding\OwnerRegistrationService;
use Illuminate\Foundation\Http\FormRequest;

class RegisterOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guest();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return OwnerRegistrationService::rules();
    }
}

<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewOnboarding', $this->route('company'));
    }

    public function rules(): array
    {
        return [];
    }
}

<?php

namespace App\Http\Requests\Onboarding;

use App\Support\CompanyOnboardingRequirements;
use Illuminate\Foundation\Http\FormRequest;

class CompanyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewOnboarding', $this->route('company'));
    }

    public function rules(): array
    {
        return CompanyOnboardingRequirements::documentRules();
    }
}

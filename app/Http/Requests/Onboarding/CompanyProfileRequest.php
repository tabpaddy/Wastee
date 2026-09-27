<?php

namespace App\Http\Requests\Onboarding;

use App\Services\Auth\CompanyAccess;
use App\Support\CompanyOnboardingRequirements;
use Illuminate\Foundation\Http\FormRequest;

class CompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('company') ? $this->user()->can('viewOnboarding', $this->route('company'))
            : app(CompanyAccess::class)->isActiveUser($this->user());
    }

    public function rules(): array
    {
        return CompanyOnboardingRequirements::profileRules($this->route('company'));
    }
}

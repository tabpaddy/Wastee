<?php

namespace App\Support;

use App\Enums\CompanyDocumentType;
use App\Enums\CompanyStatus;
use App\Enums\DocumentReviewStatus;
use App\Models\Company;
use App\Models\User;
use App\Services\Auth\CompanyAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyOnboardingRequirements
{
    public static function documentTypes(): array
    {
        return [CompanyDocumentType::RegistrationCertificate, CompanyDocumentType::WasteManagementLicense];
    }

    public static function profileRules(?Company $company = null): array
    {
        return ['name' => ['required', 'string', 'max:180'],
            'registration_number' => ['nullable', 'string', 'max:255', Rule::unique('companies')->ignore($company?->id)],
            'license_number' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'], 'phone' => ['required', 'string', 'max:40'],
            'website' => ['nullable', 'url:http,https', 'max:255']];
    }

    public static function locationRules(): array
    {
        return ['label' => ['required', 'string', 'max:255'], 'address_line' => ['required', 'string', 'max:255'],
            'community' => ['nullable', 'string', 'max:255'], 'lga_id' => ['required', 'integer', 'exists:lgas,id']];
    }

    public static function documentRules(): array
    {
        return ['document_type' => ['required', Rule::enum(CompanyDocumentType::class)],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:10240']];
    }

    public static function editable(Company $company): bool
    {
        return in_array($company->status, [CompanyStatus::Draft, CompanyStatus::CorrectionRequired, CompanyStatus::Rejected], true);
    }

    public function authorizeOwner(User $actor, Company $company, bool $editing = false): void
    {
        Gate::forUser($actor)->authorize('viewOnboarding', $company);
        if ($editing && ! self::editable($company)) {
            throw ValidationException::withMessages(['company' => 'This application is frozen in its current status.']);
        }
    }

    public function missing(Company $company, bool $approvedDocuments = false): array
    {
        $missing = [];
        foreach (['name', 'registration_number', 'license_number', 'email', 'phone'] as $field) {
            if (blank($company->$field)) {
                $missing[] = str_replace('_', ' ', $field);
            }
        }
        $owner = $company->owner()->first();
        if (! $owner || ! $owner->hasVerifiedEmail()) {
            $missing[] = 'verified owner email';
        }
        if (! $owner || ! app(CompanyAccess::class)->isActiveUser($owner)) {
            $missing[] = 'active owner';
        }
        if (! $company->memberships()->current()->where('user_id', $company->owner_user_id)->exists()) {
            $missing[] = 'current owner membership';
        }
        if (! DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.company_id', $company->id)->where('roles.company_id', $company->id)
            ->where('model_type', (new User)->getMorphClass())->where('model_id', $company->owner_user_id)
            ->where('roles.name', 'Owner')->where('guard_name', 'web')->exists()) {
            $missing[] = 'company Owner role';
        }
        if (! $company->locations()->current()->where('is_head_office', true)->exists()) {
            $missing[] = 'current head office';
        }
        $documents = $company->documents()->latestOfEachType()->get()->keyBy(fn ($document) => $document->document_type->value);
        foreach (self::documentTypes() as $type) {
            $document = $documents->get($type->value);
            if (! $document || $document->status === DocumentReviewStatus::Rejected
                || ($approvedDocuments && $document->status !== DocumentReviewStatus::Approved)) {
                $missing[] = str_replace('_', ' ', $type->value).($approvedDocuments ? ' approval' : '');
            }
        }

        return $missing;
    }

    public function assertComplete(Company $company, bool $approvedDocuments = false): void
    {
        if ($missing = $this->missing($company, $approvedDocuments)) {
            throw ValidationException::withMessages(['company' => 'Complete: '.implode(', ', $missing).'.']);
        }
    }
}

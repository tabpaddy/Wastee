<?php

namespace Tests;

use App\Models\Company;
use App\Models\Lga;
use App\Models\User;
use App\Services\Onboarding\CompanyDocumentService;
use App\Services\Onboarding\CompanyLocationService;
use App\Services\Onboarding\CompanyRegistrationService;
use App\Services\Onboarding\CompanyReviewSubmissionService;
use App\Support\CompanyOnboardingRequirements;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class Phase3TestCase extends Phase2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('company_documents');
    }

    protected function profile(): array
    {
        return ['name' => 'Green Earth Waste', 'registration_number' => 'RC-'.Str::uuid7(),
            'license_number' => 'WM-123', 'email' => 'office@example.test', 'phone' => '08012345678'];
    }

    protected function draft(?User $owner = null): array
    {
        $owner ??= User::factory()->create();

        return [$owner, app(CompanyRegistrationService::class)->create($owner, $this->profile())];
    }

    protected function ready(bool $submit = false): array
    {
        [$owner, $company] = $this->draft();
        app(CompanyLocationService::class)->establish($owner, $company, [
            'label' => 'Head office', 'address_line' => '1 Test Road', 'lga_id' => Lga::factory()->create()->id]);
        foreach (CompanyOnboardingRequirements::documentTypes() as $type) {
            app(CompanyDocumentService::class)->upload($owner, $company, [
                'document_type' => $type->value, 'document' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf')]);
        }
        if ($submit) {
            app(CompanyReviewSubmissionService::class)->submit($owner, $company);
        }

        return [$owner, $company->fresh()];
    }

    protected function acceptDocuments(User $reviewer, Company $company): void
    {
        $this->context()->runForPlatform($reviewer, function () use ($reviewer, $company): void {
            foreach ($company->documents()->latestOfEachType()->get() as $document) {
                app(CompanyDocumentService::class)->review($reviewer, $company, $document, ['status' => 'approved']);
            }
        });
    }
}

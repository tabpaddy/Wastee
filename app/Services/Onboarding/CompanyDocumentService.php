<?php

namespace App\Services\Onboarding;

use App\Enums\CompanyStatus;
use App\Enums\DocumentReviewStatus;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\User;
use App\Support\CompanyOnboardingRequirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyDocumentService
{
    public function upload(User $actor, Company $company, array $input): CompanyDocument
    {
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $company, $input, &$path): CompanyDocument {
                $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
                app(CompanyOnboardingRequirements::class)->authorizeOwner($actor, $company, true);
                $data = Validator::make($input, CompanyOnboardingRequirements::documentRules())->validate();
                $file = $data['document'];
                $name = Str::uuid7().'.'.$file->extension();
                $path = $file->storeAs($company->uuid, $name, 'company_documents');
                if (! $path) {
                    throw new \RuntimeException('Document storage failed.');
                }

                return $company->documents()->create(['document_type' => $data['document_type'],
                    'original_filename' => $name, 'disk' => 'company_documents', 'path' => $path,
                    'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(),
                    'status' => DocumentReviewStatus::Pending]);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('company_documents')->delete($path);
            }
            throw $exception;
        }
    }

    public function review(User $actor, Company $company, CompanyDocument $document, array $input): void
    {
        DB::transaction(function () use ($actor, $company, $document, $input): void {
            Gate::forUser($actor)->authorize('platform.companies.review');
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $document = $company->documents()->whereKey($document->id)->firstOrFail();
            $input['review_remarks'] = isset($input['review_remarks']) ? trim($input['review_remarks']) : null;
            $data = Validator::make($input, [
                'status' => ['required', Rule::in(['approved', 'rejected'])],
                'review_remarks' => ['nullable', 'string', 'max:4000', 'required_if:status,rejected', 'min:10'],
            ])->validate();
            if ($company->status !== CompanyStatus::PendingReview || $document->status !== DocumentReviewStatus::Pending
                || ! $company->documents()->latestOfEachType()->whereKey($document->id)->exists()) {
                throw ValidationException::withMessages(['document' => 'Only the latest pending document of an application under review can be decided.']);
            }
            $document->update([...$data, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        });
    }

    public function download(User $actor, Company $company, CompanyDocument $document): StreamedResponse
    {
        abort_unless($document->company_id === $company->id, 404);
        if (! Gate::forUser($actor)->allows('platform.companies.view')) {
            app(CompanyOnboardingRequirements::class)->authorizeOwner($actor, $company);
        }
        // Never accept disk or path from an HTTP payload.
        abort_unless($document->disk === 'company_documents'
            && str_starts_with($document->path, $company->uuid.'/')
            && ! str_contains($document->path, '..'), 404);
        abort_unless(Storage::disk('company_documents')->exists($document->path), 404);

        return Storage::disk('company_documents')->download($document->path, $document->uuid.'.'.pathinfo($document->path, PATHINFO_EXTENSION),
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}

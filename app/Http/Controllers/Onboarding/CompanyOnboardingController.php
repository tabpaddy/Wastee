<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\CompanyDocumentRequest;
use App\Http\Requests\Onboarding\CompanyLocationRequest;
use App\Http\Requests\Onboarding\CompanyProfileRequest;
use App\Http\Requests\Onboarding\SubmitCompanyRequest;
use App\Models\Company;
use App\Models\Lga;
use App\Services\Auth\CompanyAccess;
use App\Services\Onboarding\CompanyDocumentService;
use App\Services\Onboarding\CompanyLocationService;
use App\Services\Onboarding\CompanyProfileService;
use App\Services\Onboarding\CompanyRegistrationService;
use App\Services\Onboarding\CompanyReviewSubmissionService;
use App\Support\CompanyOnboardingRequirements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CompanyOnboardingController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(app(CompanyAccess::class)->isActiveUser($request->user()), 403);

        return view('onboarding.index', ['companies' => Company::ownedBy($request->user())->latest()->paginate(20)]);
    }

    public function show(Company $company): View
    {
        Gate::authorize('viewOnboarding', $company);

        return view('onboarding.show', ['company' => $company->load(['locations.state', 'locations.lga', 'documents', 'approvalLogs.actor']),
            'missing' => app(CompanyOnboardingRequirements::class)->missing($company),
            'editable' => CompanyOnboardingRequirements::editable($company),
            'lgas' => Lga::with('state')->orderBy('name')->get()]);
    }

    public function store(CompanyProfileRequest $request, CompanyRegistrationService $service): RedirectResponse
    {
        $company = $service->create($request->user(), $request->validated());

        return redirect()->route('onboarding.show', $company->uuid)->with('status', 'Company created. Complete the steps below.');
    }

    public function update(CompanyProfileRequest $request, Company $company, CompanyProfileService $service): RedirectResponse
    {
        $service->update($request->user(), $company, $request->validated());

        return back()->with('status', 'Company profile saved.');
    }

    public function location(CompanyLocationRequest $request, Company $company, CompanyLocationService $service): RedirectResponse
    {
        $service->establish($request->user(), $company, $request->validated());

        return back()->with('status', 'Head office saved.');
    }

    public function document(CompanyDocumentRequest $request, Company $company, CompanyDocumentService $service): RedirectResponse
    {
        $service->upload($request->user(), $company, $request->validated());

        return back()->with('status', 'Document uploaded. Earlier versions remain in your history.');
    }

    public function submit(SubmitCompanyRequest $request, Company $company, CompanyReviewSubmissionService $service): RedirectResponse
    {
        $service->submit($request->user(), $company);

        return back()->with('status', 'Application submitted for review.');
    }
}

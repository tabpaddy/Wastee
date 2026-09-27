<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Services\Onboarding\CompanyDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyDocumentController extends Controller
{
    public function __invoke(Request $request, Company $company, CompanyDocument $document, CompanyDocumentService $service): StreamedResponse
    {
        return $service->download($request->user(), $company, $document);
    }
}

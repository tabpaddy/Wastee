@extends('onboarding.layout')
@section('title', $company->name)
@section('content')
<a href="{{ route('onboarding.index') }}">← All applications</a>
<h1>{{ $company->name }}</h1><span class="badge">{{ str_replace('_',' ',$company->status->value) }}</span>
@if($company->review_summary)<p class="notice">{{ $company->review_summary }}</p>@endif
@if($company->status === \App\Enums\CompanyStatus::Approved)
<p><a class="button" href="{{ url('/company/'.$company->uuid) }}">Open company dashboard</a></p>
@elseif($company->status === \App\Enums\CompanyStatus::PendingReview)
<p>Your application is under review. Information and documents are frozen until a decision is made.</p>
@endif
@if($editable)
<section><h2>Application readiness</h2>
@if($missing)<p>Complete these requirements before submitting:</p><ul>@foreach($missing as $item)<li>{{ ucfirst($item) }}</li>@endforeach</ul>
@else<p>Your application is ready to submit.</p>@endif
@if($company->status === \App\Enums\CompanyStatus::Rejected)<p>Correct the issues in your rejection reason, then explicitly resubmit. Your previous decision stays in the history.</p>@endif
<form method="post" action="{{ route('onboarding.submit', $company->uuid) }}">@csrf<button @disabled(count($missing)>0)>{{ $company->submitted_at ? 'Resubmit for review' : 'Submit for review' }}</button></form></section>
@endif
<section><h2>Company profile</h2>
@if($editable)<form method="post" action="{{ route('onboarding.update', $company->uuid) }}">@csrf @method('PUT')
@include('onboarding.profile-fields')<button>Save profile</button></form>
@else<dl>@foreach(['registration_number','license_number','email','phone','website'] as $field)<dt>{{ ucfirst(str_replace('_',' ',$field)) }}</dt><dd>{{ $company->$field ?: '—' }}</dd>@endforeach</dl>@endif
</section>
<section><h2>Head office and location history</h2>
@forelse($company->locations->sortByDesc('id') as $location)
<p><strong>{{ $location->label }}</strong> — {{ $location->address_line }}, {{ $location->community }} {{ $location->lga->name }}, {{ $location->state->name }}<br>
<small>{{ $location->active_from->format('d M Y') }} – {{ $location->active_to?->format('d M Y') ?? 'Current' }}</small></p>
@empty<p>No head office registered.</p>@endforelse
@if($editable)
@if($lgas->isEmpty())<p class="notice">Location selection will be available after the platform imports verified state and LGA reference data.</p>
@else
<p>Saving a replacement closes the previous office and retains its history. An office established today can be replaced from tomorrow.</p>
<form method="post" action="{{ route('onboarding.location', $company->uuid) }}">@csrf
<label>Office label<input name="label" value="{{ old('label', 'Head office') }}" required maxlength="255"></label>
<label>Address<input name="address_line" value="{{ old('address_line') }}" required maxlength="255"></label>
<label>Community / area<input name="community" value="{{ old('community') }}" maxlength="255"></label>
<label>State / LGA<select name="lga_id" required><option value="">Select a location</option>@foreach($lgas as $lga)<option value="{{ $lga->id }}" @selected(old('lga_id') == $lga->id)>{{ $lga->state->name }} — {{ $lga->name }}</option>@endforeach</select></label>
<button>Save head office</button></form>
@endif @endif
</section>
<section><h2>Compliance documents</h2><p>Required: registration certificate and waste-management licence. PDF, JPG or PNG, up to 10 MB each. A new upload replaces the current version for review; previous versions remain below.</p>
@if($editable)<form method="post" enctype="multipart/form-data" action="{{ route('onboarding.document', $company->uuid) }}">@csrf
<label>Document type<select name="document_type">@foreach(\App\Enums\CompanyDocumentType::cases() as $type)<option value="{{ $type->value }}">{{ ucfirst(str_replace('_',' ',$type->value)) }}</option>@endforeach</select></label>
<label>File<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required></label><button>Upload document</button></form>@endif
<div class="scroll"><table><thead><tr><th>Document</th><th>Uploaded</th><th>Review</th><th>Remarks</th></tr></thead><tbody>
@foreach($company->documents->sortByDesc('id') as $document)<tr><td><a href="{{ route('onboarding.download', ['company'=>$company->uuid,'document'=>$document->uuid]) }}">{{ ucfirst(str_replace('_',' ',$document->document_type->value)) }}</a>
@if($company->documents->where('document_type', $document->document_type)->max('id') !== $document->id)<small> (previous version)</small>@endif</td>
<td>{{ $document->created_at->format('d M Y H:i') }}</td><td>{{ $document->status->value }}</td><td>{{ $document->review_remarks }}</td></tr>@endforeach
</tbody></table></div></section>
<section><h2>Review history</h2>
@forelse($company->approvalLogs->sortByDesc('id') as $log)
<p><strong>{{ ucfirst(str_replace('_',' ',$log->action->value)) }}</strong> · {{ $log->created_at->format('d M Y H:i') }}<br>{{ $log->remarks }}</p>
@empty<p>No review activity yet.</p>@endforelse
</section>
@endsection

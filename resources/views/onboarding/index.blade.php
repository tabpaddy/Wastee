@extends('onboarding.layout')
@section('content')
<h1>Your company applications</h1>
@forelse($companies as $application)
<section><h2><a href="{{ route('onboarding.show', $application->uuid) }}">{{ $application->name }}</a></h2>
<span class="badge">{{ str_replace('_',' ',$application->status->value) }}</span></section>
@empty<p>No company applications yet. Start one below.</p>@endforelse
{{ $companies->links() }}
<section><h2>Register a company</h2><p>Registration and licence numbers can be added later, but are required before submission.</p>
<form method="post" action="{{ route('onboarding.store') }}">@csrf
@include('onboarding.profile-fields')<button>Create draft application</button></form></section>
@endsection

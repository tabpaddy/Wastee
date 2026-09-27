@extends('onboarding.layout')
@section('title', 'Verify your email')
@section('content')
<h1>Verify your email</h1><section><p>Check your inbox for a verification link before starting company onboarding.</p>
<form method="post" action="{{ route('verification.send') }}">@csrf<button>Resend verification email</button></form>
<p><a href="{{ route('onboarding.index') }}">Continue after verification</a></p></section>
@endsection

@extends('onboarding.layout')
@section('title', 'Welcome')
@section('content')
<h1>Register your waste-management company</h1>
<p>Complete your company profile, upload compliance documents and submit your application for platform review.</p>
<section><h2>Company owners</h2>
@auth
<p><a class="button" href="{{ route('onboarding.index') }}">Continue company onboarding</a></p>
@else
<p><a class="button" href="{{ route('register') }}">Create an owner account</a> <a href="{{ route('login') }}">Sign in</a></p>
@endauth
</section>
<section><h2>Existing accounts</h2><p><a href="/company">Company dashboard</a> · <a href="/platform">Platform administration</a></p></section>
@endsection

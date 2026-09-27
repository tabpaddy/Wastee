@extends('onboarding.layout')
@section('title', 'Owner sign in')
@section('content')
<h1>Continue your company application</h1>
<section><form method="post" action="{{ route('login') }}">@csrf
<label>Email<input name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></label>
<label>Password<input name="password" type="password" required autocomplete="current-password"></label>
<button>Sign in</button></form></section>
<p><a href="{{ route('register') }}">Create an owner account</a> · <a href="/company/password-reset/request">Reset password</a></p>
@endsection

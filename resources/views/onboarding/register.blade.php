@extends('onboarding.layout')
@section('title', 'Register your owner account')
@section('content')
<h1>Register your owner account</h1><p>Create your account, verify your email, then register your waste-management company.</p>
<section><form method="post" action="{{ route('register') }}">@csrf
<div class="grid">
@foreach(['first_name'=>'First name','last_name'=>'Last name','email'=>'Email','phone'=>'Phone'] as $field=>$label)
<label>{{ $label }}<input name="{{ $field }}" type="{{ $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') }}" value="{{ old($field) }}" required autocomplete="{{ str_replace('_','-',$field) }}"></label>
@endforeach
<label>Password<input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
<label>Confirm password<input name="password_confirmation" type="password" required autocomplete="new-password"></label>
</div><p class="muted">Use at least 12 characters, including uppercase, lowercase, a number and a symbol.</p>
<button>Create account</button></form></section>
<p>Already registered? <a href="{{ route('login') }}">Sign in</a>.</p>
@endsection

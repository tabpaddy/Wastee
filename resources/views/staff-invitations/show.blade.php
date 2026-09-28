<x-staff-layout>
<h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Join {{ $invitation->company->name }}</h1>
<p class="mt-3 leading-7 text-slate-600">You have been invited as <strong>{{ $invitation->role_name }}</strong>.</p>
<section class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
<p class="break-words text-sm text-slate-600">Invitation for <strong>{{ $invitation->email }}</strong>. Expires {{ $invitation->expires_at->format('d M Y H:i T') }}.</p>
@auth
@if(strtolower(auth()->user()->email) !== strtolower($invitation->email))
<p class="mt-6 rounded-lg bg-amber-50 p-4 text-amber-900">This invitation is for a different email address. Sign out, reopen the email link and sign in with the invited account.</p>
@else
<form method="post" action="{{ route('staff-invitations.accept', $invitation->uuid) }}" class="mt-6">@csrf
<p class="mb-5 text-slate-600">Accepting verifies ownership of this mailbox and starts your company membership.</p>
<button class="w-full rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white hover:bg-emerald-800 focus:outline-2 focus:outline-offset-2 focus:outline-emerald-700 sm:w-auto">Accept invitation</button></form>
@endif
@else
@if($existingAccount)
<p class="mt-6 leading-7 text-slate-600">This email already has a Wastee account. Sign in to confirm your identity and accept.</p>
<a href="{{ route('login') }}" class="mt-6 inline-block rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white hover:bg-emerald-800">Sign in to continue</a>
@else
<form method="post" action="{{ route('staff-invitations.accept', $invitation->uuid) }}" class="mt-6 space-y-5">@csrf
<div class="grid gap-5 sm:grid-cols-2">
@foreach(['first_name'=>'First name','last_name'=>'Last name','phone'=>'Phone'] as $field=>$label)
<label class="block text-sm font-medium">{{ $label }}<input name="{{ $field }}" value="{{ old($field) }}" required maxlength="100" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-3 focus:border-emerald-600 focus:outline-emerald-600"></label>
@endforeach
</div>
<p class="text-sm leading-6 text-slate-600">Use at least 12 characters including uppercase, lowercase, a number and a symbol. Your invitation proves ownership of the invited email address.</p>
<div class="grid gap-5 sm:grid-cols-2">
<label class="block text-sm font-medium">Password<input name="password" type="password" required minlength="12" autocomplete="new-password" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-3"></label>
<label class="block text-sm font-medium">Confirm password<input name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-3"></label>
</div>
<button class="w-full rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white hover:bg-emerald-800 focus:outline-2 focus:outline-offset-2 focus:outline-emerald-700 sm:w-auto">Create account and accept</button>
</form>
@endif
@endauth
</section>
</x-staff-layout>

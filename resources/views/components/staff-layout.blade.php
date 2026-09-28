<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer"><title>{{ $title ?? 'Staff invitation' }} · Wastee</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head><body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
<header class="border-b border-emerald-800 bg-emerald-950 text-white"><div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-8">
<a href="/" class="text-xl font-semibold tracking-tight">Wastee</a>
@auth<form method="post" action="{{ route('logout') }}">@csrf<button class="rounded-lg border border-emerald-600 px-4 py-2 text-sm hover:bg-emerald-900">Sign out</button></form>
@else<a class="underline underline-offset-4" href="{{ route('login') }}">Sign in</a>@endauth
</div></header>
<main class="mx-auto max-w-3xl px-4 py-8 sm:px-8 sm:py-12 lg:py-16">
@if($errors->any())<div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
{{ $slot }}
</main></body></html>

<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Company onboarding') · Wastee</title>
<style>
:root{font-family:system-ui,sans-serif;color:#18352e;background:#f2f6f4}*{box-sizing:border-box}body{margin:0}header{background:#123e32;color:white;padding:20px max(24px,calc((100% - 1000px)/2));display:flex;align-items:center;gap:24px}header a{color:white}header form{margin-left:auto}main{max-width:1000px;margin:32px auto;padding:0 20px}section{background:white;padding:24px;border:1px solid #d8e3dd;border-radius:12px;margin:20px 0}h1{font-size:2rem}h2{font-size:1.2rem}p{line-height:1.6}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}label{display:block;font-weight:600;margin-bottom:14px}input,select,textarea{display:block;width:100%;padding:11px;border:1px solid #9cb4aa;border-radius:6px;margin-top:6px;font:inherit}button,.button{display:inline-block;background:#176849;color:white;padding:11px 18px;border:0;border-radius:6px;font:inherit;cursor:pointer;text-decoration:none}button:disabled{opacity:.5;cursor:default}a{color:#126643}small,.muted{color:#52685e}.notice{background:#e1f3e8;padding:16px;border-radius:8px}.errors{background:#ffe8e4;padding:18px;color:#852c21}table{border-collapse:collapse;width:100%;font-size:.9rem}td,th{text-align:left;padding:12px;border-bottom:1px solid #d8e3dd;vertical-align:top}.scroll{overflow:auto}.badge{display:inline-block;padding:5px 9px;background:#e9f0ec;border-radius:5px;text-transform:capitalize}form.inline{display:inline}header button{background:#285c4d}
</style></head><body>
<header><strong>Wastee</strong><a href="{{ route('onboarding.index') }}">Company onboarding</a>
@auth <form method="post" action="{{ route('logout') }}">@csrf<button>Sign out</button></form>
@else <a href="{{ route('login') }}">Sign in</a> @endauth</header>
<main>
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="errors" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main></body></html>

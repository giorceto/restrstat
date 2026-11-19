<!doctype html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Статистики') · Tours Club</title>
<link rel="preload" href="{{ asset('vendor/inter/inter-cyrillic-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}?v=4.6.13">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v=5">
</head>
<body>
@auth
@php $lastSync = \App\Models\SyncRun::lastOk(); $keepQ = request()->only(['from', 'to', 'basis', 'channel']); @endphp
<div class="shell">
  <aside class="side">
    <a class="brand" href="{{ route('overview') }}"><span class="mark">TC</span><span><b>Tours Club</b><small>Статистики</small></span></a>
    <nav class="nav">
      @foreach (['overview' => 'Обзор', 'destinations' => 'Дестинации', 'time' => 'Време и сезон', 'cancellations' => 'Анулации', 'suppliers' => 'Доставчици'] as $r => $label)
        <a href="{{ route($r, $r === 'suppliers' ? [] : $keepQ) }}" @class(['on' => request()->routeIs($r, $r.'.*')])>{{ $label }}</a>
      @endforeach
    </nav>
    <div class="grow"></div>
    <div class="sync">Последно теглене от SAMO<b>{{ $lastSync?->finished_at?->format('d.m.Y, H:i') ?? 'още няма' }}</b></div>
    <form method="post" action="{{ route('logout') }}">@csrf<button>Изход</button></form>
  </aside>
  <main>
    @yield('content')
  </main>
</div>
@else
@yield('content')
@endauth
<script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}?v=4.6.13"></script>
<script src="{{ asset('vendor/flatpickr/bg.js') }}?v=4.6.13"></script>
<script src="{{ asset('js/dates.js') }}?v=2"></script>
@stack('scripts')
</body>
</html>

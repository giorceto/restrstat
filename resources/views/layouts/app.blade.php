<!doctype html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Статистики') · Tours Club</title>
<link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}?v=4.6.13">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v=3">
</head>
<body>
@auth
<header class="top"><div class="top-in">
  <a class="brand" href="{{ route('overview') }}">Статистики</a>
  <nav class="tabs">
    @foreach (['overview' => 'Обзор', 'destinations' => 'Дестинации', 'time' => 'Време и сезон', 'cancellations' => 'Анулации', 'suppliers' => 'Задължения към доставчици'] as $r => $label)
      <a href="{{ route($r, in_array($r, ['suppliers']) ? [] : request()->only(['from', 'to', 'basis', 'channel'])) }}" @class(['on' => request()->routeIs($r, $r.'.*')])>{{ $label }}</a>
    @endforeach
  </nav>
  <form class="logout" method="post" action="{{ route('logout') }}">@csrf<button>Изход</button></form>
</div></header>
@endauth
<main>
@yield('content')
</main>
<script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}?v=4.6.13"></script>
<script src="{{ asset('vendor/flatpickr/bg.js') }}?v=4.6.13"></script>
<script src="{{ asset('js/dates.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>

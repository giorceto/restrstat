<!doctype html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Статистики') · Tours Club</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
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
@stack('scripts')
</body>
</html>

@extends('layouts.app')
@section('title', 'Обзор')
@php
  use App\Support\Fmt;
  $cur = null;
  foreach ($months as $i => $m) { if ($m['month'] === now()->format('Y-m')) { $cur = $i; } }
  $col = fn ($k) => array_column($months, $k);
  $maxSale = max(array_column($countries, 'sale') ?: [0]) ?: 1;
@endphp
@section('content')
@include('partials.filters', ['title' => 'Обзор'])
@if (! $lastSync)
  <div class="warn">Още няма теглене от SAMO — данните са измислени (за проба) или липсват.</div>
@endif
@if ($now['unconverted'])
  <div class="warn">{{ $now['unconverted'] }} заявки имат услуги в непозната валута (не € и не лв.) — сумите им не са точни.</div>
@endif

<div class="kpis">
  @include('partials.kpi', ['l' => 'Оборот', 'v' => Fmt::eur($now['sale']), 'd' => Fmt::change($now['sale'], $ly['sale']), 's' => 'преди година: '.Fmt::eur($ly['sale']), 'spark' => $col('sale'), 'cur' => $cur])
  @include('partials.kpi', ['l' => 'Марж', 'v' => Fmt::eur($now['margin']), 'd' => Fmt::change($now['margin'], $ly['margin']), 's' => 'преди година: '.Fmt::eur($ly['margin']), 'spark' => $col('margin'), 'cur' => $cur])
  @include('partials.kpi', ['l' => 'Заявки', 'v' => Fmt::num($now['claims']), 'd' => Fmt::change($now['claims'], $ly['claims']), 's' => 'преди година: '.Fmt::num($ly['claims']), 'spark' => $col('claims'), 'cur' => $cur])
  @include('partials.kpi', ['l' => 'Туристи', 'v' => Fmt::num($now['pax']), 'd' => Fmt::change($now['pax'], $ly['pax']), 's' => 'преди година: '.Fmt::num($ly['pax']), 'spark' => $col('pax'), 'cur' => $cur])
</div>

<div class="stats">
  <div class="stat"><div class="l">Марж %</div><div class="v">@pct($now['margin_pct'])</div><div class="s">преди година: @pct($ly['margin_pct'])</div></div>
  <div class="stat"><div class="l">Средно на заявка</div><div class="v">@eur($now['avg_sale'])</div><div class="s">преди година: @eur($ly['avg_sale'])</div></div>
  <div class="stat"><div class="l">Средно нощувки</div><div class="v">@num($now['avg_nights'], 1)</div><div class="s">преди година: @num($ly['avg_nights'], 1)</div></div>
  <div class="stat"><div class="l">Анулирани</div><div class="v">@pct($now['cancel_pct'])</div><div class="s">{{ Fmt::num($now['cancelled']) }} заявки · преди година @pct($ly['cancel_pct'])</div></div>
  <div class="stat"><div class="l">Купуват средно</div><div class="v">{{ $avgLead !== null ? Fmt::num($avgLead).' дни' : '—' }}</div><div class="s">преди тръгване</div></div>
</div>

<div class="grid g-main" style="margin-bottom:16px">
  <div class="card">
    <div class="card-h"><h2>Оборот по месеци <span class="note">— {{ $f->basis === 'travel' ? 'на настаняване' : 'на продажба' }}</span></h2>
      <div class="legend"><span><i class="c1"></i>{{ $f->from->year }}</span><span><i class="cly"></i>{{ $f->from->year - 1 }}</span></div></div>
    <div class="chart"><canvas data-chart="{{ Fmt::chart(array_column($months, 'label'), [(string) $f->from->year => $col('sale'), (string) ($f->from->year - 1) => $col('sale_ly')]) }}"></canvas></div>
  </div>
  <div>
    <div class="card">
      <div class="card-h"><h2>Оборот по канали</h2></div>
      <div class="share">@foreach ($channels as $i => $c)@if ($c['share'])<span class="c{{ $i + 1 }}" style="width: {{ $c['share'] }}%" title="{{ $c['label'] }}: {{ Fmt::pct($c['share']) }}"></span>@endif @endforeach</div>
      <div class="list">
        @foreach ($channels as $i => $c)
          <div class="r"><span class="dot c{{ $i + 1 }}"></span><span class="n">{{ $c['label'] }}</span><b>@eur($c['sale'])</b><span class="muted" style="width:44px; text-align:right">{{ $c['share'] !== null ? Fmt::num($c['share']).' %' : '—' }}</span></div>
        @endforeach
      </div>
    </div>
    <div class="card">
      <div class="card-h"><h2>Топ дестинации</h2><a class="note" href="{{ route('destinations', $f->query()) }}">всички →</a></div>
      <div class="list">
        @forelse ($countries as $c)
          <div>
            <div class="r"><span class="n"><a href="{{ route('destinations', $f->query(['country' => $c['id']])) }}">{{ $c['name'] }}</a></span><b>@eur($c['sale'])</b>
              <span style="width:64px; text-align:right; font-size:12px; font-weight:500" class="{{ ($c['change_pct'] ?? 0) >= 0 ? 'up' : 'down' }}">{{ $c['change_pct'] !== null ? ($c['change_pct'] > 0 ? '+' : '').Fmt::num($c['change_pct'], 1).' %' : '—' }}</span></div>
            <div class="track"><span style="width: {{ max(1, round(100 * $c['sale'] / $maxSale)) }}%"></span></div>
          </div>
        @empty
          <div class="muted">Няма данни за периода.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>

@include('partials.months', ['months' => $months, 'f' => $f])
@include('partials.chartjs')
@endsection

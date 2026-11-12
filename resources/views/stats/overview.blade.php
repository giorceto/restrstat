@extends('layouts.app')
@section('title', 'Обзор')
@section('content')
<h1>Обзор <span class="note">— {{ \App\Support\Fmt::date($f->from->toDateString()) }} – {{ \App\Support\Fmt::date($f->to->toDateString()) }}, {{ \App\Services\Filters::BASES[$f->basis] }}; сравнение със същия период на {{ $f->from->year - 1 }}</span></h1>
@include('partials.filters')
@if (! $lastSync)
  <div class="warn">Още няма теглене от SAMO — данните са измислени (за проба) или липсват.</div>
@endif
@if ($now['unconverted'])
  <div class="warn">{{ $now['unconverted'] }} заявки имат услуги в непозната валута (не € и не лв.) — сумите им не са точни.</div>
@endif
@php $kpi = [
  ['Оборот', \App\Support\Fmt::eur($now['sale']), \App\Support\Fmt::change($now['sale'], $ly['sale']), \App\Support\Fmt::eur($ly['sale'])],
  ['Марж', \App\Support\Fmt::eur($now['margin']), \App\Support\Fmt::change($now['margin'], $ly['margin']), \App\Support\Fmt::eur($ly['margin'])],
  ['Марж %', \App\Support\Fmt::pct($now['margin_pct']), null, \App\Support\Fmt::pct($ly['margin_pct'])],
  ['Заявки', \App\Support\Fmt::num($now['claims']), \App\Support\Fmt::change($now['claims'], $ly['claims']), \App\Support\Fmt::num($ly['claims'])],
  ['Туристи', \App\Support\Fmt::num($now['pax']), \App\Support\Fmt::change($now['pax'], $ly['pax']), \App\Support\Fmt::num($ly['pax'])],
  ['Средно на заявка', \App\Support\Fmt::eur($now['avg_sale']), \App\Support\Fmt::change($now['avg_sale'], $ly['avg_sale']), \App\Support\Fmt::eur($ly['avg_sale'])],
  ['Средно нощувки', \App\Support\Fmt::num($now['avg_nights'], 1), null, \App\Support\Fmt::num($ly['avg_nights'], 1)],
  ['Анулирани', \App\Support\Fmt::pct($now['cancel_pct']), null, \App\Support\Fmt::pct($ly['cancel_pct']).' · '.$now['cancelled'].' бр.'],
]; @endphp
<div class="kpis">
  @foreach ($kpi as [$l, $v, $d, $prev])
    <div class="kpi"><div class="l">{{ $l }}</div><div class="v">{{ $v }}</div>
      <div class="s">{!! $d !== null ? \App\Support\Fmt::delta($d).' · ' : '' !!}преди година: {{ $prev }}</div></div>
  @endforeach
</div>
<div class="grid g2">
  <div class="card">
    <h2>Оборот по месеци ({{ $f->basis === 'travel' ? 'на настаняване' : 'на продажба' }})</h2>
    <div class="chart"><canvas data-chart="{{ \App\Support\Fmt::chart(array_column($months, 'label'), ['Сега' => array_column($months, 'sale'), 'Година по-рано' => array_column($months, 'sale_ly')]) }}"></canvas></div>
  </div>
  <div class="card">
    <h2>Топ дестинации</h2>
    <div class="tbl-wrap"><table>
      <thead><tr><th>Държава</th><th>Заявки</th><th>Оборот</th><th>Марж %</th><th>спрямо {{ $f->from->year - 1 }}</th></tr></thead>
      <tbody>
      @foreach ($countries as $c)
        <tr><td><a href="{{ route('destinations', $f->query(['country' => $c['id']])) }}">{{ $c['name'] }}</a></td>
          <td>@num($c['claims'])</td><td>@eur($c['sale'])</td><td>@pct($c['margin_pct'])</td><td>@delta($c['change_pct'])</td></tr>
      @endforeach
      </tbody>
    </table></div>
    <p class="note">Средно {{ $avgLead !== null ? \App\Support\Fmt::num($avgLead).' дни' : '—' }} от продажбата до тръгването.
      @if ($lastSync) Последно теглене от SAMO: {{ $lastSync->finished_at->format('d.m.Y H:i') }}. @endif</p>
  </div>
</div>
@include('partials.months', ['months' => $months, 'f' => $f])
@include('partials.chartjs')
@endsection

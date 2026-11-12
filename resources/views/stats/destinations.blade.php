@extends('layouts.app')
@section('title', 'Дестинации')
@php
  $levelName = ['country' => 'Държава', 'town' => 'Курорт / град', 'hotel' => 'Хотел'][$level];
  $keep = array_filter(['country' => $country, 'town' => $town]);
  $sortLink = fn ($col) => route('destinations', $f->query($keep + ['sort' => $col]));
  $cols = ['claims' => 'Заявки', 'pax' => 'Туристи', 'avg_nights' => 'Ср. нощувки', 'sale' => 'Оборот', 'margin' => 'Марж',
           'margin_pct' => 'Марж %', 'avg_sale' => 'Ср. на заявка', 'change_pct' => 'Оборот спрямо '.($f->from->year - 1)];
@endphp
@section('content')
<h1>Дестинации</h1>
@include('partials.filters', ['keep' => $keep + ['sort' => $sort]])
<div class="crumbs">
  <a href="{{ route('destinations', $f->query(['sort' => $sort])) }}">Всички държави</a>
  @if ($country) › <a href="{{ route('destinations', $f->query(['country' => $country, 'sort' => $sort])) }}">{{ $countryName }}</a>@endif
  @if ($town) › {{ $townName }}@endif
</div>
<div class="card">
  <h2>По {{ mb_strtolower(['country' => 'държави', 'town' => 'курорти', 'hotel' => 'хотели'][$level]) }} <span class="note">— без анулираните; щракни колона за подреждане{{ $level !== 'hotel' ? ', име — за подробности' : '' }}</span></h2>
  <div class="tbl-wrap"><table>
    <thead><tr><th>{{ $levelName }}</th>
      @foreach ($cols as $k => $l)<th><a href="{{ $sortLink($k) }}" @class(['on' => $sort === $k])>{{ $l }}{{ $sort === $k ? ' ↓' : '' }}</a></th>@endforeach
      <th>Дял</th></tr></thead>
    <tbody>
    @php $max = max(array_column($rows, 'sale') ?: [0]); $sum = array_sum(array_column($rows, 'sale')); @endphp
    @forelse ($rows as $r)
      <tr>
        <td>
          @if ($level === 'country' && $r['id'] !== null)<a href="{{ route('destinations', $f->query(['country' => $r['id'], 'sort' => $sort])) }}">{{ $r['name'] }}</a>
          @elseif ($level === 'town' && $r['id'] !== null)<a href="{{ route('destinations', $f->query(['country' => $country, 'town' => $r['id'], 'sort' => $sort])) }}">{{ $r['name'] }}</a>
          @else {{ $r['name'] }} @endif
        </td>
        <td>@num($r['claims'])</td><td>@num($r['pax'])</td><td>@num($r['avg_nights'], 1)</td><td>@eur($r['sale'])</td><td>@eur($r['margin'])</td>
        <td>@pct($r['margin_pct'])</td><td>@eur($r['avg_sale'])</td><td>@delta($r['change_pct'])</td>
        <td style="min-width:110px"><div class="bar" style="width: {{ $max > 0 ? max(1, round(100 * $r['sale'] / $max)) : 0 }}%" title="{{ $sum > 0 ? round(100 * $r['sale'] / $sum, 1) : 0 }} %"></div></td>
      </tr>
    @empty
      <tr><td colspan="10" class="muted">Няма заявки за този период.</td></tr>
    @endforelse
    @if ($rows)
      @php $t = \App\Services\Statistics::metrics((object) ['claims' => array_sum(array_column($rows, 'claims')), 'pax' => array_sum(array_column($rows, 'pax')),
        'sale' => $sum, 'net' => array_sum(array_column($rows, 'net')), 'margin' => array_sum(array_column($rows, 'margin'))]); @endphp
      <tr class="total"><td>Общо</td><td>@num($t['claims'])</td><td>@num($t['pax'])</td><td></td><td>@eur($t['sale'])</td><td>@eur($t['margin'])</td>
        <td>@pct($t['margin_pct'])</td><td>@eur($t['avg_sale'])</td>
        <td>@delta(\App\Support\Fmt::change($t['sale'], array_sum(array_column($rows, 'sale_ly'))))</td><td></td></tr>
    @endif
    </tbody>
  </table></div>
  <p class="note">Дестинацията на заявката е от основната ѝ услуга (хотела с най-голяма цена). Маржът = продажна − нетна − комисиона на агенцията.</p>
</div>
@endsection

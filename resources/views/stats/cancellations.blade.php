@extends('layouts.app')
@section('title', 'Анулации')
@php use App\Support\Fmt; @endphp
@section('content')
@include('partials.filters', ['title' => 'Анулации'])
<div class="kpis">
  <div class="kpi"><div class="l">Анулирани</div><div class="v">@pct($total['cancel_pct'])</div><div class="s">{{ $total['cancelled'] }} от {{ $total['all'] }} заявки · преди година: @pct($ly['cancel_pct'])</div></div>
  <div class="kpi"><div class="l">Останали активни</div><div class="v">@num($total['claims'])</div><div class="s">преди година: @num($ly['claims'])</div></div>
</div>
<div class="grid g2">
  @foreach ([['По канал', $byChannel, 'Канал'], ['По срок преди тръгването', $byLead, 'Анулирана … преди тръгване']] as [$title, $rows, $head])
  <div class="card">
    <h2>{{ $title }}</h2>
    <div class="tbl-wrap"><table>
      <thead><tr><th>{{ $head }}</th>@if ($rows === $byLead)<th>Анулирани</th><th>Дял от анулациите</th>@else<th>Всички</th><th>Анулирани</th><th>%</th>@endif<th>Оборот на анулираните</th></tr></thead>
      <tbody>
      @foreach ($rows as $r)
        <tr><td>{{ $r['label'] }}</td>@if ($rows === $byLead)<td>@num($r['cancelled'])</td><td>@pct($r['pct'])</td>@else<td>@num($r['all'])</td><td>@num($r['cancelled'])</td><td>@pct($r['pct'])</td>@endif<td>@eur($r['cancelled_sale'])</td></tr>
      @endforeach
      </tbody>
    </table></div>
  </div>
  @endforeach
</div>
<div class="card">
  <h2>По държави</h2>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Държава</th><th>Всички</th><th>Анулирани</th><th>%</th><th>Оборот на анулираните</th></tr></thead>
    <tbody>
    @foreach ($byCountry as $r)
      <tr><td>{{ $r['label'] }}</td><td>@num($r['all'])</td><td>@num($r['cancelled'])</td><td>@pct($r['pct'])</td><td>@eur($r['cancelled_sale'])</td></tr>
    @endforeach
    </tbody>
  </table></div>
  <p class="note">Анулирана е заявка със статус 3, 4 или 5 в SAMO. Срокът е от датата на анулиране до датата на тръгване.</p>
</div>
@endsection

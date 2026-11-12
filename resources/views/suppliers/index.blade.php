@extends('layouts.app')
@section('title', 'Задължения към доставчици')
@php use App\Support\Fmt; $v = fn ($k) => $filters[$k]?->toDateString(); @endphp
@section('content')
<h1>Задължения към доставчици</h1>
<form class="card filters" method="get">
  <label>Фирма
    <select name="supplier" required style="min-width:260px">
      <option value="">— избери —</option>
      @foreach ($suppliers as $s)<option value="{{ $s->samo_inc }}" @selected($supplier === (int) $s->samo_inc)>{{ $s->name ?? '№ '.$s->samo_inc }}</option>@endforeach
    </select>
  </label>
  <label>Продадени от <input type="date" name="sold_from" value="{{ $v('sold_from') }}"></label>
  <label>до <input type="date" name="sold_to" value="{{ $v('sold_to') }}"></label>
  <label>Настаняване от <input type="date" name="in_from" value="{{ $v('in_from') }}"></label>
  <label>до <input type="date" name="in_to" value="{{ $v('in_to') }}"></label>
  @if ($all)<input type="hidden" name="all" value="1">@endif
  <button class="btn">Покажи</button>
  <span class="note">
    @if ($all)<a href="{{ request()->fullUrlWithQuery(['all' => null]) }}">само турските</a>@else<a href="{{ request()->fullUrlWithQuery(['all' => 1]) }}">всички доставчици</a>@endif
    · <a href="{{ route('suppliers.setup') }}">кои са турски</a>
  </span>
</form>
@if ($suppliers->isEmpty())
  <div class="warn">Няма отбелязани турски доставчици. Отбележи ги в <a href="{{ route('suppliers.setup') }}">„кои са турски“</a>.</div>
@endif
@if ($result)
  <div class="kpis">
    <div class="kpi"><div class="l">Дължимо на {{ $supplierName ?? '№ '.$supplier }}</div><div class="v">@eur($result['total'])</div><div class="s">нетни цени, без анулираните</div></div>
    <div class="kpi"><div class="l">Заявки</div><div class="v">@num($result['claims'])</div><div class="s">{{ $result['services'] }} услуги</div></div>
  </div>
  @if ($result['unconverted'])<div class="warn">{{ $result['unconverted'] }} услуги са в непозната валута — не са в общата сума (виж колона „в друга валута“).</div>@endif
  <div class="card">
    <h2 style="display:flex; justify-content:space-between; align-items:center">По заявки <a class="btn2" style="font-weight:400" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Excel (CSV)</a></h2>
    <div class="tbl-wrap"><table>
      <thead><tr><th>Заявка</th><th>Продадена</th><th>Настаняване</th><th>Напускане</th><th>Туристи</th><th>Хотел / курорт</th><th>Услуги</th><th>Нетна (€)</th><th>В друга валута</th></tr></thead>
      <tbody>
      @forelse ($result['rows'] as $r)
        <tr><td>{{ $r->claim }}</td><td>{{ Fmt::date(substr($r->created_at, 0, 10)) }}</td><td>{{ Fmt::date($r->date_from) }}</td><td>{{ Fmt::date($r->date_to) }}</td>
          <td>{{ $r->pax ?? '—' }}</td><td>{{ $r->place ?? '—' }}</td><td>{{ $r->services }}</td><td>{{ Fmt::num($r->net, 2) }}</td>
          <td class="muted">{{ implode(', ', $r->other) }}</td></tr>
      @empty
        <tr><td colspan="9" class="muted">Няма услуги от тази фирма за избраните периоди.</td></tr>
      @endforelse
      @if ($result['rows'])<tr class="total"><td colspan="7">Общо</td><td>{{ Fmt::num($result['total'], 2) }}</td><td></td></tr>@endif
      </tbody>
    </table></div>
    <p class="note">Периодът на продажба е по датата на създаване на заявката; на настаняване — по началото на услугата.
      Плащанията към доставчика още не са приспаднати.</p>
  </div>
@endif
@endsection

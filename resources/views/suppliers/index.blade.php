@extends('layouts.app')
@section('title', 'Задължения към доставчици')
@php use App\Support\Fmt; $v = fn ($k) => $filters[$k]?->toDateString(); @endphp
@section('content')
<h1>Задължения към доставчици</h1>
<form class="card filters" method="get">
  <label>Фирма
    <select name="supplier" style="min-width:260px">
      <option value="">— всички (салдо) —</option>
      @foreach ($suppliers as $s)<option value="{{ $s->samo_inc }}" @selected($supplier === (int) $s->samo_inc)>{{ $s->name ?? '№ '.$s->samo_inc }}</option>@endforeach
    </select>
  </label>
  <label>Продадени от <input type="text" class="date" placeholder="дд.мм.гггг" autocomplete="off" name="sold_from" value="{{ $v('sold_from') }}"></label>
  <label>до <input type="text" class="date" placeholder="дд.мм.гггг" autocomplete="off" name="sold_to" value="{{ $v('sold_to') }}"></label>
  <label>Настаняване от <input type="text" class="date" placeholder="{{ $inFromDefault && $filters['in_from'] ? $filters['in_from']->format('d.m.Y') : 'дд.мм.гггг' }}" autocomplete="off" name="in_from" value="{{ $inFromDefault ? '' : $v('in_from') }}"></label>
  <label>до <input type="text" class="date" placeholder="дд.мм.гггг" autocomplete="off" name="in_to" value="{{ $v('in_to') }}"></label>
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
@if (session('status'))<p class="ok">{{ session('status') }}</p>@endif
<form method="post" action="{{ route('suppliers.balance-from') }}" class="note" style="margin:-6px 0 12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap">
  @csrf
  <span>Задълженията се водят от настаняване</span>
  <input type="text" class="date" placeholder="{{ \App\Support\Fmt::date(config('restrstat.supplier_start')) }}" autocomplete="off" name="balance_from" value="{{ $balanceFrom?->toDateString() }}">
  <button class="btn" style="padding:4px 10px">Запиши</button>
  <span>— по-стари само по справка: задай по-ранно „Настаняване от“ горе</span>
  @error('balance_from')<span class="err">{{ $message }}</span>@enderror
</form>
@if (! $supplier && $suppliers->isNotEmpty())
  <div class="card">
    <h2>Салдо по фирми <span class="note">— активни заявки{{ $balanceFrom ? ' с настаняване от '.$balanceFrom->format('d.m.Y') : '' }}, без филтрите по период</span></h2>
    <div class="tbl-wrap"><table>
      <thead><tr><th>Фирма</th><th>Дължимо общо</th><th>с настаняване до днес</th><th>Платено</th><th>Остатък общо</th><th>Остатък до днес</th><th>Последно плащане</th></tr></thead>
      <tbody>
      @php $sum = ['owed' => 0, 'owed_to_date' => 0, 'paid' => 0, 'balance' => 0, 'due_now' => 0]; @endphp
      @foreach ($suppliers as $s)
        @php $b = $balances[(int) $s->samo_inc]; foreach ($sum as $k => $_) { $sum[$k] += $b[$k]; } @endphp
        <tr><td><a href="{{ route('suppliers', array_filter(['supplier' => $s->samo_inc, 'all' => $all ?: null])) }}">{{ $s->name ?? '№ '.$s->samo_inc }}</a></td>
          <td>@eur2($b['owed'])</td><td>@eur2($b['owed_to_date'])</td><td>@eur2($b['paid'])</td><td>@eur2($b['balance'])</td>
          <td @class(['down' => $b['due_now'] > 0])>@eur2($b['due_now'])</td><td>{{ Fmt::date($b['last_paid']) }}</td></tr>
      @endforeach
      <tr class="total"><td>Общо</td><td>@eur2($sum['owed'])</td><td>@eur2($sum['owed_to_date'])</td><td>@eur2($sum['paid'])</td><td>@eur2($sum['balance'])</td><td>@eur2($sum['due_now'])</td><td></td></tr>
      </tbody>
    </table></div>
    <p class="note">„Остатък до днес“ = нетните цени на услугите с настаняване до днес − платеното. Червено — дължим.</p>
  </div>
@endif
@if ($result)
  @php $b = $balances[$supplier]; $hasFilter = array_filter($inFromDefault ? array_diff_key($filters, ['in_from' => 1]) : $filters); @endphp
  <div class="kpis">
    <div class="kpi"><div class="l">{{ $hasFilter ? 'Дължимо за избрания период' : 'Дължимо от '.$balanceFrom?->format('d.m.Y') }}</div><div class="v">@eur2($result['total'])</div><div class="s">{{ $result['claims'] }} заявки, {{ $result['services'] }} услуги, без анулираните</div></div>
    <div class="kpi"><div class="l">Платено общо</div><div class="v">@eur2($b['paid'])</div><div class="s">{{ $payments->count() }} плащания · последно {{ Fmt::date($b['last_paid']) }}</div></div>
    <div class="kpi"><div class="l">Остатък общо</div><div class="v">@eur2($b['balance'])</div><div class="s">{{ $balanceFrom ? 'настаняване от '.$balanceFrom->format('d.m.Y') : 'всички заявки' }} ({{ Fmt::num($b['owed'], 2) }} €) − платено</div></div>
    <div class="kpi"><div class="l">Остатък до днес</div><div class="v {{ $b['due_now'] > 0 ? 'down' : '' }}">@eur2($b['due_now'])</div><div class="s">настанени до днес ({{ Fmt::num($b['owed_to_date'], 2) }} €) − платено</div></div>
  </div>

  <div class="card">
    <h2>Плащания към {{ $supplierName ?? '№ '.$supplier }}</h2>
    <form method="post" action="{{ route('suppliers.payments.store', $supplier) }}" class="filters" style="margin-bottom:12px">
      @csrf
      <label>Дата <input type="text" class="date" placeholder="дд.мм.гггг" autocomplete="off" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" required></label>
      <label>Сума (€) <input type="text" name="amount" inputmode="decimal" value="{{ old('amount') }}" required style="width:120px"></label>
      <label>Номер на документ <input type="text" name="document_no" value="{{ old('document_no') }}" maxlength="64"></label>
      <label>Сметка / банка <input type="text" name="bank" value="{{ old('bank') }}" maxlength="100" list="banks"></label>
      <datalist id="banks">@foreach ($banks as $bank)<option value="{{ $bank }}">@endforeach</datalist>
      <button class="btn">Добави плащане</button>
    </form>
    @if ($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    <div class="tbl-wrap"><table>
      <thead><tr><th>Дата</th><th>Сума</th><th>Документ</th><th>Сметка / банка</th><th>Въведено</th><th></th></tr></thead>
      <tbody>
      @forelse ($payments as $p)
        <tr><td>{{ $p->paid_on->format('d.m.Y') }}</td><td>{{ Fmt::num((float) $p->amount, 2) }} €</td><td>{{ $p->document_no ?? '—' }}</td><td>{{ $p->bank ?? '—' }}</td>
          <td class="muted">{{ $p->created_at?->format('d.m.Y H:i') }}{{ $p->user ? ' · '.$p->user->name : '' }}</td>
          <td><form method="post" action="{{ route('suppliers.payments.destroy', $p) }}" onsubmit="return confirm('Да изтрия ли плащането от {{ $p->paid_on->format('d.m.Y') }} за {{ Fmt::num((float) $p->amount, 2) }} €?')">
            @csrf @method('DELETE')<button class="linkbtn">изтрий</button></form></td></tr>
      @empty
        <tr><td colspan="6" class="muted">Още няма въведени плащания.</td></tr>
      @endforelse
      @if ($payments->isNotEmpty())<tr class="total"><td>Общо</td><td>{{ Fmt::num($b['paid'], 2) }} €</td><td colspan="4"></td></tr>@endif
      </tbody>
    </table></div>
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
      Плащанията се приспадат от общото салдо горе (не по периоди).</p>
  </div>
@endif
@endsection

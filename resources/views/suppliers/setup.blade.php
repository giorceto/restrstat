@extends('layouts.app')
@section('title', 'Турски доставчици')
@section('content')
<div class="head"><div class="t"><h1>Настройка на доставчиците</h1><div class="sub">Кои са турски и кои се водят към доставчика на хотела</div></div></div>
@if (session('status'))<p class="ok">{{ session('status') }}</p>@endif
<form method="post" class="card">
  @csrf
  <p class="note">Отбелязаните се показват в „Задължения към доставчици“. Познатите по държава (Турция) се отбелязват сами при тегленето; каквото смениш тук, остава.<br>
    „Разходът е към доставчика на хотела“ — напр. AYT transfers: трансферът в заявката се дължи на фирмата, от която е хотелът в същата заявка (ако няма хотел — остава на самия доставчик).</p>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Доставчик</th><th>Държава</th><th>Турски</th><th>Разходът е към доставчика на хотела</th></tr></thead>
    <tbody>
    @foreach ($suppliers as $s)
      <tr><td>{{ $s->name ?? '№ '.$s->samo_inc }} <span class="muted">({{ $s->samo_inc }})</span></td>
        <td>{{ $s->country ? ($countries[$s->country] ?? '№ '.$s->country) : '—' }}</td>
        <td><input type="hidden" name="shown[]" value="{{ $s->samo_inc }}">
          <input type="checkbox" name="turkish[]" value="{{ $s->samo_inc }}" @checked($s->is_turkish)> {{ $s->turkish_manual ? '(ръчно)' : '' }}</td>
        <td><input type="checkbox" name="follows_hotel[]" value="{{ $s->samo_inc }}" @checked($s->follows_hotel)></td></tr>
    @endforeach
    </tbody>
  </table></div>
  <p><button class="btn">Запиши</button> <a href="{{ route('suppliers') }}">← обратно</a></p>
</form>
@endsection

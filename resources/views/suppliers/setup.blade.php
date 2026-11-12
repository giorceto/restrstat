@extends('layouts.app')
@section('title', 'Турски доставчици')
@section('content')
<h1>Кои доставчици са турски</h1>
@if (session('status'))<p class="ok">{{ session('status') }}</p>@endif
<form method="post" class="card">
  @csrf
  <p class="note">Отбелязаните се показват в „Задължения към доставчици“. Познатите по държава (Турция) се отбелязват сами при тегленето; каквото смениш тук, остава.</p>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Доставчик</th><th>Държава</th><th>Турски</th></tr></thead>
    <tbody>
    @foreach ($suppliers as $s)
      <tr><td>{{ $s->name ?? '№ '.$s->samo_inc }} <span class="muted">({{ $s->samo_inc }})</span></td>
        <td>{{ $s->country ? ($countries[$s->country] ?? '№ '.$s->country) : '—' }}</td>
        <td><input type="hidden" name="shown[]" value="{{ $s->samo_inc }}">
          <input type="checkbox" name="turkish[]" value="{{ $s->samo_inc }}" @checked($s->is_turkish)> {{ $s->turkish_manual ? '(ръчно)' : '' }}</td></tr>
    @endforeach
    </tbody>
  </table></div>
  <p><button class="btn">Запиши</button> <a href="{{ route('suppliers') }}">← обратно</a></p>
</form>
@endsection

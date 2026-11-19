@extends('layouts.app')
@section('title', 'Вход')
@section('content')
<div class="login-wrap">
  <div class="card login">
    <div class="brand" style="padding:0 0 18px"><span class="mark">TC</span><span><b>Tours Club</b><small>Статистики</small></span></div>
    <form method="post" action="{{ route('login') }}">
      @csrf
      <label>Имейл <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
      <label>Парола <input type="password" name="password" required autocomplete="current-password"></label>
      @error('email')<div class="err">{{ $message }}</div>@enderror
      <button class="btn">Вход</button>
    </form>
  </div>
</div>
@endsection

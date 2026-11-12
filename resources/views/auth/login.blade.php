@extends('layouts.app')
@section('title', 'Вход')
@section('content')
<div class="card login">
  <h1>Статистики — вход</h1>
  <form method="post" action="{{ route('login') }}">
    @csrf
    <label>Имейл <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" style="width:100%"></label>
    <label>Парола <input type="password" name="password" required autocomplete="current-password" style="width:100%"></label>
    @error('email')<div class="err">{{ $message }}</div>@enderror
    <button class="btn">Вход</button>
  </form>
</div>
@endsection

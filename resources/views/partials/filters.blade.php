@php
  $y = now()->year;
  $preset = fn ($from, $to) => request()->fullUrlWithQuery(['from' => $from, 'to' => $to]);
@endphp
<form class="card filters" method="get">
  @foreach ($keep ?? [] as $k => $v)
    @if ($v !== null)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
  @endforeach
  <label>От <input type="text" class="date" placeholder="дд.мм.гггг" autocomplete="off" name="from" value="{{ $f->from->toDateString() }}"></label>
  <label>До <input type="text" class="date" placeholder="дд.мм.гггг" autocomplete="off" name="to" value="{{ $f->to->toDateString() }}"></label>
  <label>Периодът е
    <select name="basis">
      @foreach (\App\Services\Filters::BASES as $k => $label)<option value="{{ $k }}" @selected($f->basis === $k)>{{ $label }}</option>@endforeach
    </select>
  </label>
  <label>Канал
    <select name="channel">
      @foreach (\App\Services\Filters::CHANNELS as $k => $label)<option value="{{ $k }}" @selected($f->channel === $k)>{{ $label }}</option>@endforeach
    </select>
  </label>
  <button class="btn">Покажи</button>
  <div class="presets">
    <a href="{{ $preset("$y-01-01", "$y-12-31") }}">{{ $y }}</a>
    <a href="{{ $preset(($y - 1).'-01-01', ($y - 1).'-12-31') }}">{{ $y - 1 }}</a>
    <a href="{{ $preset(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()) }}">този месец</a>
    <a href="{{ $preset(now()->subDays(29)->toDateString(), now()->toDateString()) }}">последните 30 дни</a>
    <a href="{{ $preset("$y-01-01", now()->toDateString()) }}">от началото на годината</a>
  </div>
</form>

{{-- Заглавие на страницата + филтрите (сменят се веднага). $title, $sub, $keep --}}
@php
  $y = now()->year;
  $preset = fn ($from, $to) => request()->fullUrlWithQuery(['from' => $from, 'to' => $to]);
  $isPreset = fn ($from, $to) => $f->from->toDateString() === $from && $f->to->toDateString() === $to;
  $presets = [
    [(string) $y, "$y-01-01", "$y-12-31"],
    [(string) ($y - 1), ($y - 1).'-01-01', ($y - 1).'-12-31'],
    ['от началото на годината', "$y-01-01", now()->toDateString()],
    ['този месец', now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
    ['последните 30 дни', now()->subDays(29)->toDateString(), now()->toDateString()],
  ];
@endphp
<form class="head autosubmit" method="get">
  <div class="t">
    <h1>{{ $title }}</h1>
    <div class="sub">{{ $sub ?? \App\Support\Fmt::date($f->from->toDateString()).' – '.\App\Support\Fmt::date($f->to->toDateString()).' · сравнение със същия период на '.($f->from->year - 1) }}</div>
  </div>
  @foreach ($keep ?? [] as $k => $v)
    @if ($v !== null)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
  @endforeach
  <div class="controls">
    <div class="seg" role="radiogroup" aria-label="Периодът е по дата на">
      <label><input type="radio" name="basis" value="created" @checked($f->basis === 'created')><span>Продажба</span></label>
      <label><input type="radio" name="basis" value="travel" @checked($f->basis === 'travel')><span>Настаняване</span></label>
    </div>
    <span class="pill"><span class="muted">от</span>
      <input type="text" class="date" name="from" value="{{ $f->from->toDateString() }}" aria-label="От">
      <span class="muted">до</span>
      <input type="text" class="date" name="to" value="{{ $f->to->toDateString() }}" aria-label="До"></span>
    <span class="pill"><select name="channel" aria-label="Канал">
      @foreach (\App\Services\Filters::CHANNELS as $k => $label)<option value="{{ $k }}" @selected($f->channel === $k)>{{ $k === 'all' ? 'Всички канали' : $label }}</option>@endforeach
    </select></span>
    <noscript><button class="btn">Покажи</button></noscript>
  </div>
</form>
<div class="chips">
  @foreach ($presets as [$label, $from, $to])
    <a href="{{ $preset($from, $to) }}" @class(['on' => $isPreset($from, $to)])>{{ $label }}</a>
  @endforeach
</div>

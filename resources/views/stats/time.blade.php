@extends('layouts.app')
@section('title', 'Време и сезон')
@php use App\Support\Fmt; @endphp
@section('content')
@include('partials.filters', ['title' => 'Време и сезон', 'keep' => ['season' => $year, 'country' => $country]])

<div class="grid g2">
  <div class="card">
    <h2>Оборот по месеци ({{ $f->basis === 'travel' ? 'на настаняване' : 'на продажба' }})</h2>
    <div class="legend"><span><i class="c1"></i>{{ $f->from->year }}</span><span><i class="cly"></i>{{ $f->from->year - 1 }}</span></div>
    <div class="chart"><canvas data-chart="{{ Fmt::chart(array_column($months, 'label'), ['Сега' => array_column($months, 'sale'), 'Година по-рано' => array_column($months, 'sale_ly')]) }}"></canvas></div>
  </div>
  <div class="card">
    <h2>Кога се купува — дни преди тръгването</h2>
    <div class="tbl-wrap"><table>
      <thead><tr><th>Срок</th><th>Заявки</th><th>Дял</th><th>Оборот</th><th>Марж %</th><th>Ср. на заявка</th><th></th></tr></thead>
      <tbody>
      @php $maxShare = max(array_column($lead, 'share') ?: [0]) ?: 1; @endphp
      @foreach ($lead as $l)
        <tr><td>{{ $l['label'] }}</td><td>@num($l['claims'])</td><td>@pct($l['share'])</td><td>@eur($l['sale'])</td><td>@pct($l['margin_pct'])</td><td>@eur($l['avg_sale'])</td>
          <td style="min-width:90px"><div class="bar" style="width: {{ round(100 * ($l['share'] ?? 0) / $maxShare) }}%"></div></td></tr>
      @endforeach
      </tbody>
    </table></div>
    <p class="note">Средно {{ $avgLead !== null ? Fmt::num($avgLead).' дни' : '—' }} от продажбата до тръгването (за избрания период и канал).</p>
  </div>
</div>

<div class="card">
  <h2>Сезон {{ $year }} към днешна дата спрямо {{ $year - 1 }}
    <span class="note">— пътувания през {{ $year }}, продадени до {{ Fmt::date($season['cut']) }}; за {{ $year - 1 }} — продадени до същия ден преди година</span></h2>
  <form method="get" class="filters" style="margin-bottom:10px">
    @foreach ($f->query() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <label>Сезон <select name="season" onchange="this.form.submit()">
      @for ($y = now()->year + 1; $y >= now()->year - 5; $y--)<option @selected($y === $year)>{{ $y }}</option>@endfor
    </select></label>
  </form>
  <div class="kpis">
    @foreach ([['Оборот', 'sale', 'eur'], ['Заявки', 'claims', 'num'], ['Туристи', 'pax', 'num'], ['Марж', 'margin', 'eur']] as [$l, $k, $t])
      <div class="kpi"><div class="l">{{ $l }}</div><div class="v">{{ $t === 'eur' ? Fmt::eur($season['now'][$k]) : Fmt::num($season['now'][$k]) }}</div>
        <div class="s">{!! Fmt::delta(Fmt::change($season['now'][$k], $season['ly'][$k])) !!} · {{ $year - 1 }}: {{ $t === 'eur' ? Fmt::eur($season['ly'][$k]) : Fmt::num($season['ly'][$k]) }}</div></div>
    @endforeach
  </div>
  <div class="legend" style="margin-bottom:10px"><span><i class="c1"></i>{{ $year }}</span><span><i class="cly"></i>{{ $year - 1 }}</span></div>
  <div class="chart"><canvas data-chart="{{ Fmt::chart(array_column($season['months'], 'label'), [(string) $year => array_column($season['months'], 'sale'), (string) ($year - 1) => array_column($season['months'], 'sale_ly')]) }}"></canvas></div>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Месец на настаняване</th><th>Заявки {{ $year }}</th><th>Заявки {{ $year - 1 }}</th><th>Туристи {{ $year }}</th><th>Туристи {{ $year - 1 }}</th><th>Оборот {{ $year }}</th><th>Оборот {{ $year - 1 }}</th><th>Промяна</th></tr></thead>
    <tbody>
    @foreach ($season['months'] as $m)
      <tr><td>{{ $m['label'] }}</td><td>@num($m['claims'])</td><td>@num($m['claims_ly'])</td><td>@num($m['pax'])</td><td>@num($m['pax_ly'])</td><td>@eur($m['sale'])</td><td>@eur($m['sale_ly'])</td><td>@delta($m['change_pct'])</td></tr>
    @endforeach
    </tbody>
  </table></div>
</div>

<div class="card">
  <h2>Натовареност — туристи на почивка по седмици, април–октомври {{ $year }}</h2>
  <form method="get" class="filters" style="margin-bottom:10px">
    @foreach ($f->query(['season' => $year]) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <label>Държава <select name="country" onchange="this.form.submit()">
      <option value="">всички</option>
      @foreach ($countries as $id => $name)<option value="{{ $id }}" @selected($country === (int) $id)>{{ $name }}</option>@endforeach
    </select></label>
  </form>
  <div class="chart"><canvas data-chart="{{ Fmt::chart(array_column($occupancy, 'label'), ['Туристи' => array_column($occupancy, 'pax')], 'num', 'line') }}"></canvas></div>
  <details><summary class="note">Таблица</summary>
    <div class="tbl-wrap"><table><thead><tr><th>Седмица от</th><th>Туристи</th><th>Заявки</th></tr></thead><tbody>
      @foreach ($occupancy as $w)<tr><td>{{ Fmt::date($w['week']) }}</td><td>@num($w['pax'])</td><td>@num($w['claims'])</td></tr>@endforeach
    </tbody></table></div>
  </details>
  <p class="note">Без анулираните. Заявка без брой туристи се брои за 1.</p>
</div>

@include('partials.months', ['months' => $months, 'f' => $f])
@include('partials.chartjs')
@endsection

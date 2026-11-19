{{-- $l етикет, $v стойност, $d промяна в %, $s пояснение, $spark стойности, $cur индекс --}}
<div class="kpi">
  <div class="l">{{ $l }}</div>
  <div class="row"><span class="v">{{ $v }}</span>{!! \App\Support\Fmt::badge($d ?? null) !!}</div>
  @if (! empty($spark)){!! \App\Support\Fmt::spark($spark, $cur ?? null) !!}@endif
  <div class="s">{{ $s }}</div>
</div>

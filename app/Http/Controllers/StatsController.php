<?php

namespace App\Http\Controllers;

use App\Models\SyncRun;
use App\Services\Filters;
use App\Services\Statistics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function __construct(private Statistics $stats) {}

    public function overview(Request $request)
    {
        $f = Filters::fromRequest($request);
        $now = $this->stats->totals($f);
        $ly = $this->stats->totals($f->lastYear());

        return view('stats.overview', [
            'f' => $f, 'now' => $now, 'ly' => $ly,
            'months' => $this->stats->byMonth($f),
            'countries' => array_slice($this->stats->byPlace($f, 'country'), 0, 8),
            'avgLead' => $this->stats->avgLead($f),
            'lastSync' => SyncRun::lastOk(),
        ]);
    }

    public function destinations(Request $request)
    {
        $f = Filters::fromRequest($request);
        $country = $request->integer('country') ?: null;
        $town = $request->integer('town') ?: null;
        $level = $town ? 'hotel' : ($country ? 'town' : 'country');
        $sort = in_array($request->query('sort'), ['sale', 'margin', 'margin_pct', 'claims', 'pax', 'avg_sale', 'avg_nights', 'change_pct'], true)
            ? $request->query('sort') : 'sale';
        $rows = $this->stats->byPlace($f, $level, $country, $town);
        usort($rows, fn ($a, $b) => ($b[$sort] ?? -INF) <=> ($a[$sort] ?? -INF));

        return view('stats.destinations', [
            'f' => $f, 'rows' => $rows, 'level' => $level, 'sort' => $sort,
            'country' => $country, 'town' => $town,
            'countryName' => $country ? ($this->stats->names('country', [$country])[$country] ?? "№ $country") : null,
            'townName' => $town ? ($this->stats->names('town', [$town])[$town] ?? "№ $town") : null,
            'total' => $this->stats->totals($f),
        ]);
    }

    public function time(Request $request)
    {
        $f = Filters::fromRequest($request);
        $year = (int) ($request->integer('season') ?: CarbonImmutable::today()->year);
        $year = max(2000, min(2100, $year));
        $occFrom = CarbonImmutable::create($year, 4, 1);
        $occTo = CarbonImmutable::create($year, 10, 31);
        $country = $request->integer('country') ?: null;

        return view('stats.time', [
            'f' => $f, 'months' => $this->stats->byMonth($f),
            'lead' => $this->stats->leadTime($f), 'avgLead' => $this->stats->avgLead($f),
            'season' => $this->stats->seasonToDate($year, $f->channel), 'year' => $year,
            'occupancy' => $this->stats->occupancy($occFrom, $occTo, $f->channel, $country),
            'country' => $country,
            'countries' => DB::table('places')->where('kind', 'country')->orderBy('name')->pluck('name', 'samo_inc'),
        ]);
    }

    public function cancellations(Request $request)
    {
        $f = Filters::fromRequest($request, 'travel');

        return view('stats.cancellations', [
            'f' => $f, 'total' => $this->stats->totals($f), 'ly' => $this->stats->totals($f->lastYear()),
            'byCountry' => $this->stats->cancellations($f, 'country'),
            'byChannel' => $this->stats->cancellations($f, 'channel'),
            'byLead' => $this->stats->cancellations($f, 'lead'),
        ]);
    }
}

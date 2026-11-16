<?php

namespace App\Http\Controllers;

use App\Services\Filters;
use App\Services\Statistics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Задължения към турските доставчици: фирма + период на продажба + период на настаняване. */
class SupplierController extends Controller
{
    public function index(Request $request, Statistics $stats)
    {
        $all = $request->boolean('all');
        $suppliers = DB::table('partners')->where('is_supplier', true)
            ->when(! $all, fn ($q) => $q->where('is_turkish', true))
            ->orderBy('name')->get(['samo_inc', 'name']);
        $supplier = $request->integer('supplier') ?: null;
        $d = fn (string $k) => Filters::date($request->query($k));
        $filters = ['sold_from' => $d('sold_from'), 'sold_to' => $d('sold_to'), 'in_from' => $d('in_from'), 'in_to' => $d('in_to')];
        $result = $supplier ? $stats->obligations($supplier, ...array_values($filters)) : null;
        if ($result && $request->query('export') === 'csv') {
            return $this->csv($supplier, $result);
        }

        return view('suppliers.index', [
            'suppliers' => $suppliers, 'supplier' => $supplier, 'all' => $all, 'filters' => $filters, 'result' => $result,
            'supplierName' => $supplier ? DB::table('partners')->where('samo_inc', $supplier)->value('name') : null,
        ]);
    }

    private function csv(int $supplier, array $result)
    {
        $name = DB::table('partners')->where('samo_inc', $supplier)->value('name') ?? $supplier;

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // за да го отвори Excel с кирилица
            fputcsv($out, ['Заявка', 'Продадена', 'Настаняване', 'Напускане', 'Туристи', 'Хотел/курорт', 'Услуги', 'Нетна (€)', 'В друга валута'], ';');
            foreach ($result['rows'] as $r) {
                fputcsv($out, [$r->claim, substr($r->created_at, 0, 10), $r->date_from, $r->date_to, $r->pax, $r->place,
                    $r->services, number_format($r->net, 2, ',', ''), implode(', ', $r->other)], ';');
            }
            fputcsv($out, ['Общо', '', '', '', '', '', $result['services'], number_format($result['total'], 2, ',', '')], ';');
            fclose($out);
        }, 'zadalzhenia-'.preg_replace('/[^\w-]+/u', '-', (string) $name).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function setup(Request $request)
    {
        return view('suppliers.setup', [
            'suppliers' => DB::table('partners')->where('is_supplier', true)->orderByDesc('is_turkish')->orderBy('name')
                ->get(['samo_inc', 'name', 'is_turkish', 'turkish_manual', 'country']),
            'countries' => DB::table('places')->where('kind', 'country')->pluck('name', 'samo_inc'),
        ]);
    }

    public function saveSetup(Request $request)
    {
        $turkish = array_map('intval', (array) $request->input('turkish', []));
        $shown = array_map('intval', (array) $request->input('shown', []));
        DB::transaction(function () use ($turkish, $shown) {
            foreach (DB::table('partners')->whereIn('samo_inc', $shown)->get(['samo_inc', 'is_turkish']) as $p) {
                $want = in_array((int) $p->samo_inc, $turkish, true);
                if ((bool) $p->is_turkish !== $want) {
                    DB::table('partners')->where('samo_inc', $p->samo_inc)->update(['is_turkish' => $want, 'turkish_manual' => true]);
                }
            }
        });

        return redirect()->route('suppliers.setup')->with('status', 'Записано.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\SupplierPayment;
use App\Services\Filters;
use App\Services\Statistics;
use App\Support\Settings;
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

        $ids = $supplier ? [$supplier] : $suppliers->pluck('samo_inc')->map(fn ($i) => (int) $i)->all();
        $balanceFrom = Filters::date(Settings::get('balance_from'));
        $balances = $stats->balances($ids, $balanceFrom);

        return view('suppliers.index', [
            'suppliers' => $suppliers, 'supplier' => $supplier, 'all' => $all, 'filters' => $filters, 'result' => $result,
            'supplierName' => $supplier ? DB::table('partners')->where('samo_inc', $supplier)->value('name') : null,
            'balances' => $balances, 'balanceFrom' => $balanceFrom,
            'payments' => $supplier ? SupplierPayment::with('user')->where('supplier', $supplier)->orderByDesc('paid_on')->orderByDesc('id')->get() : collect(),
            'banks' => SupplierPayment::whereNotNull('bank')->distinct()->orderBy('bank')->pluck('bank'),
        ]);
    }

    public function storePayment(Request $request, int $supplier)
    {
        abort_unless(DB::table('partners')->where('samo_inc', $supplier)->exists(), 404);
        $request->merge(['amount' => str_replace([' ', ','], ['', '.'], (string) $request->input('amount'))]);
        $data = $request->validate([
            'paid_on' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'document_no' => ['nullable', 'string', 'max:64'],
            'bank' => ['nullable', 'string', 'max:100'],
        ], [], ['paid_on' => 'дата', 'amount' => 'сума', 'document_no' => 'номер на документ', 'bank' => 'сметка/банка']);
        $date = Filters::date($data['paid_on']);
        if (! $date) {
            return back()->withInput()->withErrors(['paid_on' => 'Датата трябва да е дд.мм.гггг.']);
        }
        SupplierPayment::create([
            'supplier' => $supplier, 'paid_on' => $date->toDateString(), 'amount' => round((float) $data['amount'], 2),
            'document_no' => $data['document_no'] ?: null, 'bank' => $data['bank'] ?: null, 'user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Плащането е записано.');
    }

    public function saveBalanceFrom(Request $request)
    {
        $value = trim((string) $request->input('balance_from'));
        $date = $value === '' ? null : Filters::date($value);
        if ($value !== '' && ! $date) {
            return back()->withErrors(['balance_from' => 'Датата трябва да е дд.мм.гггг.']);
        }
        Settings::set('balance_from', $date?->toDateString());

        return back()->with('status', $date ? 'Салдото се смята от настаняване '.$date->format('d.m.Y').'.' : 'Салдото се смята от всички заявки.');
    }

    public function destroyPayment(SupplierPayment $payment)
    {
        $payment->delete();

        return back()->with('status', 'Плащането е изтрито.');
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

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Statistics;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/suppliers')->assertRedirect('/login');
    }

    public function test_login_and_all_pages_render_with_demo_data(): void
    {
        (new DemoSeeder)->run();
        User::create(['name' => 'G', 'email' => 'g@example.com', 'password' => 'secret12345']);
        $this->post('/login', ['email' => 'g@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'g@example.com', 'password' => 'secret12345'])->assertRedirect('/');

        $this->get('/')->assertOk()->assertSee('Оборот')->assertSee('Турция');
        $this->get('/destinations')->assertOk()->assertSee('Гърция');
        $this->get('/destinations?country=2')->assertOk()->assertSee('Анталия');
        $this->get('/destinations?country=2&town=21&sort=margin_pct')->assertOk()->assertSee('Демо хотел');
        $this->get('/time?basis=travel&channel=online')->assertOk()->assertSee('Натовареност');
        $this->get('/cancellations')->assertOk()->assertSee('По държави');
        $this->get('/suppliers')->assertOk()->assertSee('Демо TR Travel')->assertDontSee('Демо Aegean Partner');
        $this->get('/suppliers?all=1')->assertOk()->assertSee('Демо Aegean Partner');
        $this->get('/suppliers?supplier=901&in_from=2026-06-01&in_to=2026-06-30')->assertOk()->assertSee('Дължимо');
        $this->get('/suppliers?supplier=901&export=csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get('/suppliers/setup')->assertOk();
        $this->post('/suppliers/setup', ['shown' => [901, 903], 'turkish' => [903]])->assertRedirect('/suppliers/setup');
        $this->assertEquals(0, DB::table('partners')->where('samo_inc', 901)->value('is_turkish'));
        $this->assertEquals(1, DB::table('partners')->where('samo_inc', 903)->value('turkish_manual'));
    }

    public function test_obligations_filter_by_sale_and_check_in_and_skip_cancelled(): void
    {
        $claim = fn ($inc, $created, $from, $cancelled = false) => ['samo_inc' => $inc, 'partner' => 1, 'channel' => 'own', 'created_at' => "$created 10:00:00",
            'created_month' => substr($created, 0, 7), 'date_from' => $from, 'date_to' => $from, 'from_month' => substr($from, 0, 7),
            'cancelled' => $cancelled, 'sale' => 0, 'net' => 0, 'commission' => 0, 'margin' => 0, 'unconverted' => false, 'synced_at' => now()];
        DB::table('claims')->insert([$claim(1, '2026-01-10', '2026-07-01'), $claim(2, '2026-02-10', '2026-08-01'),
            $claim(3, '2026-01-15', '2026-07-05', true), $claim(4, '2025-12-20', '2026-07-03')]);
        $svc = fn ($inc, $claim, $from, $net, $unc = false) => ['samo_inc' => $inc, 'claim' => $claim, 'supplier' => 901, 'date_from' => $from,
            'net' => $net, 'sale' => 0, 'unconverted' => $unc, 'net_original' => $net, 'net_currency' => $unc ? 'USD' : '€'];
        DB::table('services')->insert([$svc(1, 1, '2026-07-01', 100.10), $svc(2, 1, '2026-07-02', 50), $svc(3, 2, '2026-08-01', 70),
            $svc(4, 3, '2026-07-05', 999), $svc(5, 4, '2026-07-03', 30), $svc(6, 1, '2026-07-01', 500, true)]);

        $d = fn ($s) => CarbonImmutable::parse($s);
        $r = (new Statistics)->obligations(901, $d('2026-01-01'), $d('2026-12-31'), $d('2026-07-01'), $d('2026-07-31'));
        $this->assertSame(150.1, $r['total']);
        $this->assertSame(1, $r['claims']);
        $this->assertSame(1, $r['unconverted']);

        $r = (new Statistics)->obligations(901, null, null, $d('2026-07-01'), $d('2026-08-31'));
        $this->assertSame(250.1, $r['total']);
        $this->assertSame(3, $r['claims']);
    }
}

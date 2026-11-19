<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Statistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayeeRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_supplier_follows_hotel_supplier(): void
    {
        DB::table('partners')->insert([
            ['samo_inc' => 901, 'name' => 'Ирелс', 'is_turkish' => true, 'is_supplier' => true],
            ['samo_inc' => 902, 'name' => 'Друг DMC', 'is_turkish' => true, 'is_supplier' => true],
            ['samo_inc' => 950, 'name' => 'AYT transfers', 'is_turkish' => true, 'is_supplier' => true],
        ]);
        $claim = fn ($inc) => ['samo_inc' => $inc, 'partner' => 1, 'channel' => 'own', 'created_at' => '2026-11-02 10:00:00',
            'created_month' => '2026-11', 'date_from' => '2026-12-01', 'synced_at' => now()];
        DB::table('claims')->insert([$claim(1), $claim(2), $claim(3)]);
        $svc = fn ($inc, $claim, $supplier, $hotel, $net) => ['samo_inc' => $inc, 'claim' => $claim, 'supplier' => $supplier, 'payee' => $supplier,
            'hotel' => $hotel, 'date_from' => '2026-12-01', 'net' => $net];
        DB::table('services')->insert([
            $svc(1, 1, 901, 501, 1000), $svc(2, 1, 950, null, 40),   // хотел от Ирелс + трансфер → Ирелс
            $svc(3, 2, 902, 502, 800), $svc(4, 2, 950, null, 35),    // хотел от друг → на другия
            $svc(5, 3, 950, null, 30),                               // само трансфер → остава на AYT
        ]);

        $this->actingAs(User::create(['name' => 'G', 'email' => 'g@example.com', 'password' => 'secret12345']));
        $this->post('/suppliers/setup', ['shown' => [901, 902, 950], 'turkish' => [901, 902, 950], 'follows_hotel' => [950]])->assertRedirect();
        $this->assertEquals(1, DB::table('partners')->where('samo_inc', 950)->value('follows_hotel'));

        $b = (new Statistics)->balances([901, 902, 950]);
        $this->assertSame(1040.0, $b[901]['owed']);
        $this->assertSame(835.0, $b[902]['owed']);
        $this->assertSame(30.0, $b[950]['owed']);

        $this->get('/suppliers?supplier=901')->assertOk()->assertSee('вкл. AYT transfers 40,00');

        // махнато — всичко се връща на самия доставчик
        $this->post('/suppliers/setup', ['shown' => [901, 902, 950], 'turkish' => [901, 902, 950]])->assertRedirect();
        $this->assertSame(105.0, (new Statistics)->balances([950])[950]['owed']);
    }
}

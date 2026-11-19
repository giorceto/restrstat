<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): void
    {
        DB::table('partners')->insert(['samo_inc' => 901, 'name' => 'TR DMC', 'is_turkish' => true, 'is_supplier' => true]);
        $claim = fn ($inc, $from, $cancelled = false) => ['samo_inc' => $inc, 'partner' => 1, 'channel' => 'own', 'created_at' => '2026-01-10 10:00:00',
            'created_month' => '2026-01', 'date_from' => $from, 'cancelled' => $cancelled, 'synced_at' => now()];
        DB::table('claims')->insert([$claim(1, now()->subDays(10)->toDateString()), $claim(2, now()->addDays(30)->toDateString()),
            $claim(3, now()->subDays(5)->toDateString(), true)]);
        $svc = fn ($inc, $claim, $from, $net) => ['samo_inc' => $inc, 'claim' => $claim, 'supplier' => 901, 'date_from' => $from, 'net' => $net];
        DB::table('claims')->insert($claim(4, '2026-08-01'));
        DB::table('services')->insert([['samo_inc' => 4, 'claim' => 4, 'supplier' => 901, 'date_from' => '2026-08-01', 'net' => 7777]]);
        DB::table('services')->insert([$svc(1, 1, now()->subDays(10)->toDateString(), 1000), $svc(2, 2, now()->addDays(30)->toDateString(), 500),
            $svc(3, 3, now()->subDays(5)->toDateString(), 9999)]);
    }

    public function test_payments_are_entered_and_subtracted_from_balance(): void
    {
        $this->seedData();
        $user = User::create(['name' => 'Георги', 'email' => 'g@example.com', 'password' => 'secret12345']);
        $this->actingAs($user);

        $this->from('/suppliers?supplier=901')->post('/suppliers/901/payments', ['paid_on' => '05.11.2026', 'amount' => '300,50',
            'document_no' => 'TR-1', 'bank' => 'Банка ДСК'])->assertRedirect('/suppliers?supplier=901');
        $p = DB::table('supplier_payments')->first();
        $this->assertSame('2026-11-05', substr($p->paid_on, 0, 10));
        $this->assertEquals(300.50, (float) $p->amount);
        $this->assertEquals($user->id, $p->user_id);

        $this->post('/suppliers/901/payments', ['paid_on' => '31.02.2026', 'amount' => '10'])->assertSessionHasErrors('paid_on');
        $this->post('/suppliers/901/payments', ['paid_on' => '01.11.2026', 'amount' => '-5'])->assertSessionHasErrors('amount');
        $this->post('/suppliers/999/payments', ['paid_on' => '01.11.2026', 'amount' => '5'])->assertNotFound();

        // от 01.11.2026: 1500 дължимо (анулираната и августовската не се броят), 1000 от тях — настанени до днес; платено 300,50
        $this->get('/suppliers?supplier=901')->assertOk()
            ->assertSee('1 199,50 €')   // остатък общо
            ->assertSee('699,50 €')     // остатък до днес
            ->assertSee('TR-1')->assertSee('Банка ДСК')->assertDontSee('7 777,00');
        $this->get('/suppliers')->assertOk()->assertSee('Салдо по фирми')->assertSee('1 199,50 €');

        // начална дата на салдото: услугата отпреди 10 дни отпада → 500 − 300,50
        $this->post('/suppliers/balance-from', ['balance_from' => now()->format('d.m.Y')])->assertRedirect();
        $this->get('/suppliers?supplier=901')->assertSee('199,50 €')->assertSee('-300,50 €');
        $this->post('/suppliers/balance-from', ['balance_from' => '99.99.2026'])->assertSessionHasErrors('balance_from');
        $this->post('/suppliers/balance-from', ['balance_from' => ''])->assertRedirect();
        $this->get('/suppliers?supplier=901')->assertSee('1 199,50 €');
        // по-старото (август) — само по справка
        $this->get('/suppliers?supplier=901&in_from=01.08.2026')->assertSee('7 777,00')->assertSee('Дължимо за избрания период');

        $id = DB::table('supplier_payments')->value('id');
        $this->delete("/suppliers/payments/$id")->assertRedirect();
        $this->assertDatabaseCount('supplier_payments', 0);
    }
}

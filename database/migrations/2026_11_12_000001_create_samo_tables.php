<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Копие от SAMO-Tour само с това, което трябва за статистиките. Без лични данни на клиенти:
 * никакви имена, ЕГН, телефони, имейли (claim.contact не се тегли). Туристите — само като брой.
 */
return new class extends Migration
{
    public function up(): void
    {
        // държави, градове/курорти, хотели (dbo.state, dbo.town, dbo.hotel)
        Schema::create('places', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('kind', ['country', 'town', 'hotel']);
            $table->unsignedInteger('samo_inc');
            $table->string('name', 190)->nullable();
            $table->unsignedInteger('parent')->nullable(); // samo_inc на града (за хотел) или на държавата (за град)
            $table->unique(['kind', 'samo_inc'], 'uq_place');
        });

        // партньорите (dbo.partner): клиенти (за кого е заявката) и доставчици (на услугите)
        Schema::create('partners', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('samo_inc')->unique();
            $table->string('name', 190)->nullable();
            $table->unsignedInteger('country')->nullable();  // dbo.partner.state, ако го има
            $table->boolean('is_turkish')->default(false);   // турски доставчик — за задълженията
            $table->boolean('turkish_manual')->default(false); // избрано ръчно — тегленето не го сменя
            $table->boolean('is_supplier')->default(false);  // има услуги от него
            $table->dateTime('synced_at')->nullable();
        });

        Schema::create('claims', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('samo_inc')->unique();    // dbo.claim.inc — номерът на заявката
            $table->unsignedInteger('partner')->nullable();   // за кого е
            $table->string('channel', 8);                     // own / online / agency
            $table->dateTime('created_at');                   // cdatetime
            $table->char('created_month', 7);                 // гггг-мм
            $table->date('date_from')->nullable();            // datebeg — настаняване
            $table->date('date_to')->nullable();
            $table->char('from_month', 7)->nullable();
            $table->smallInteger('nights')->nullable();
            $table->integer('lead_days')->nullable();         // дни от създаването до тръгването
            $table->smallInteger('status')->nullable();
            $table->boolean('cancelled')->default(false);     // статус 3, 4, 5
            $table->date('cancelled_on')->nullable();
            $table->integer('cancel_lead_days')->nullable();  // дни от анулирането до тръгването
            $table->unsignedSmallInteger('pax')->nullable();  // брой туристи (само броят!)
            $table->unsignedInteger('country')->nullable();   // от основната услуга
            $table->unsignedInteger('town')->nullable();
            $table->unsignedInteger('hotel')->nullable();
            $table->decimal('sale', 12, 2)->default(0);       // Σ cost в евро
            $table->decimal('net', 12, 2)->default(0);        // Σ net в евро
            $table->decimal('commission', 12, 2)->default(0); // комисиона на агенцията в евро (cost × % / 100)
            $table->decimal('margin', 12, 2)->default(0);     // sale − net − commission
            $table->decimal('commission_pct', 6, 2)->nullable();
            $table->boolean('unconverted')->default(false);   // има услуга в непозната валута
            $table->dateTime('changed_at')->nullable();       // edate
            $table->dateTime('synced_at');
            $table->index(['created_at', 'channel'], 'ix_claim_created');
            $table->index(['date_from', 'channel'], 'ix_claim_from');
            $table->index(['country', 'town', 'hotel'], 'ix_claim_place');
        });

        // услугите в заявката (dbo.[order])
        Schema::create('services', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('samo_inc')->unique();   // dbo.[order].inc
            $table->unsignedInteger('claim');                // dbo.claim.inc
            $table->unsignedInteger('supplier')->nullable(); // доставчикът (dbo.partner.inc)
            $table->unsignedInteger('country')->nullable();
            $table->unsignedInteger('town')->nullable();
            $table->unsignedInteger('hotel')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->smallInteger('status')->nullable();
            $table->decimal('sale', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);      // в евро — това дължим на доставчика
            $table->string('net_currency', 16)->nullable(); // валутата, в която е нетната цена
            $table->decimal('net_original', 12, 2)->nullable();
            $table->boolean('unconverted')->default(false);
            $table->index('claim', 'ix_service_claim');
            $table->index(['supplier', 'date_from'], 'ix_service_supplier');
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->increments('id');
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->boolean('full')->default(false);
            $table->unsignedInteger('claims')->default(0);
            $table->unsignedInteger('services')->default(0);
            $table->text('error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('services');
        Schema::dropIfExists('claims');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('places');
    }
};

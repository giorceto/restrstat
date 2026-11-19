<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * На кого дължим услугата. Обикновено — доставчикът ѝ; при доставчик „към хотела“ (напр. AYT transfers) —
 * доставчикът на хотела в същата заявка.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->boolean('follows_hotel')->default(false)->after('is_supplier');
        });
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('payee')->nullable()->after('supplier');
            $table->index(['payee', 'date_from'], 'ix_service_payee');
        });
        DB::table('services')->update(['payee' => DB::raw('supplier')]);
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('ix_service_payee');
            $table->dropColumn('payee');
        });
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('follows_hotel');
        });
    }
};

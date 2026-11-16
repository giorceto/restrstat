<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Плащанията към доставчиците — в SAMO ги няма, въвеждат се тук след превода. Само в евро. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('supplier');            // dbo.partner.inc
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);               // в евро
            $table->string('document_no', 64)->nullable();  // номер на превода / документа
            $table->string('bank', 100)->nullable();        // от коя наша сметка / банка
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->index(['supplier', 'paid_on'], 'ix_sp_supplier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};

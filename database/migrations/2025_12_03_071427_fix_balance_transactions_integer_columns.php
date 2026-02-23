<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Ubah tipe data kolom amount, balance_before, balance_after menjadi INTEGER
        Schema::table('balance_transactions', function (Blueprint $table) {
            $table->integer('amount')->change();
            $table->integer('balance_before')->change();
            $table->integer('balance_after')->change();
        });
    }

    public function down()
    {
        Schema::table('balance_transactions', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
            $table->decimal('balance_before', 10, 2)->change();
            $table->decimal('balance_after', 10, 2)->change();
        });
    }
};
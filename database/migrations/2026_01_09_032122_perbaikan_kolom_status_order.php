<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Perpanjang kolom order_status menjadi VARCHAR(20)
            $table->string('order_status', 20)->change();
            
            // Perpanjang kolom payment_status menjadi VARCHAR(20)
            $table->string('payment_status', 20)->change();
            
            // Perpanjang kolom priority menjadi VARCHAR(20)
            $table->string('priority', 20)->change();
            
            // Periksa kolom lain yang mungkin perlu diperpanjang
            $table->string('size', 10)->change(); // Untuk ukuran seperti 'XXXL'
            $table->string('color', 50)->change();
            $table->string('payment_method', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Kembalikan ke ukuran semula jika perlu rollback
            $table->string('order_status', 15)->change();
            $table->string('payment_status', 15)->change();
            $table->string('priority', 15)->change();
            $table->string('size', 5)->change();
            $table->string('color', 30)->change();
            $table->string('payment_method', 15)->change();
        });
    }
};
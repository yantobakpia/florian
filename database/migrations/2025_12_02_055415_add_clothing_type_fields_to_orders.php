<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_clothing_type_fields_to_orders.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Cek dulu apakah tabel clothing_types ada
            if (Schema::hasTable('clothing_types')) {
                // Tambahkan kolom clothing_type_id jika belum ada
                if (!Schema::hasColumn('orders', 'clothing_type_id')) {
                    $table->unsignedBigInteger('clothing_type_id')->nullable();
                }
            }
            
            // Tambahkan kolom custom_clothing_type jika belum ada
            if (!Schema::hasColumn('orders', 'custom_clothing_type')) {
                $table->string('custom_clothing_type')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Hanya hapus jika kolom tersebut ada
            if (Schema::hasColumn('orders', 'clothing_type_id')) {
                $table->dropColumn('clothing_type_id');
            }
            
            if (Schema::hasColumn('orders', 'custom_clothing_type')) {
                $table->dropColumn('custom_clothing_type');
            }
        });
    }
};
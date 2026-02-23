<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_clothing_type_id_to_orders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Cek apakah kolom clothing_type_id sudah ada
            if (!Schema::hasColumn('orders', 'clothing_type_id')) {
                $table->unsignedBigInteger('clothing_type_id')->nullable();
            }
            
            // Cek apakah kolom custom_clothing_type sudah ada
            if (!Schema::hasColumn('orders', 'custom_clothing_type')) {
                $table->string('custom_clothing_type')->nullable()->after('clothing_type_id');
            }
            
            // Hapus kolom material_id jika ada
            if (Schema::hasColumn('orders', 'material_id')) {
                $table->dropForeign(['material_id']);
                $table->dropColumn('material_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Kembalikan ke state sebelumnya
            if (Schema::hasColumn('orders', 'clothing_type_id')) {
                $table->dropColumn('clothing_type_id');
            }
            
            if (Schema::hasColumn('orders', 'custom_clothing_type')) {
                $table->dropColumn('custom_clothing_type');
            }
            
            // Kembalikan material_id jika di-down
            if (!Schema::hasColumn('orders', 'material_id')) {
                $table->unsignedBigInteger('material_id')->nullable();
            }
        });
    }
};
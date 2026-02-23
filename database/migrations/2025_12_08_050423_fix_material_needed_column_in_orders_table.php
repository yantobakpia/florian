<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Ubah kolom material_needed untuk memiliki default value
            if (Schema::hasColumn('orders', 'material_needed')) {
                $table->decimal('material_needed', 10, 2)
                      ->default(0)
                      ->nullable(false)
                      ->change();
            } else {
                // Jika kolom tidak ada, tambahkan
                $table->decimal('material_needed', 10, 2)
                      ->default(0)
                      ->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Untuk rollback
            if (Schema::hasColumn('orders', 'material_needed')) {
                $table->decimal('material_needed', 10, 2)
                      ->nullable()
                      ->change();
            }
        });
    }
};
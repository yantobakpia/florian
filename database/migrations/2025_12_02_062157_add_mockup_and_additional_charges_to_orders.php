<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_mockup_and_additional_charges_to_orders.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Tambahkan kolom mockup_image jika belum ada
            if (!Schema::hasColumn('orders', 'mockup_image')) {
                $table->string('mockup_image')->nullable()->after('reference_image');
            }
            
            // Tambahkan kolom additional_charges_items jika belum ada
            if (!Schema::hasColumn('orders', 'additional_charges_items')) {
                $table->json('additional_charges_items')->nullable()->after('additional_charges');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'mockup_image')) {
                $table->dropColumn('mockup_image');
            }
            
            if (Schema::hasColumn('orders', 'additional_charges_items')) {
                $table->dropColumn('additional_charges_items');
            }
        });
    }
};
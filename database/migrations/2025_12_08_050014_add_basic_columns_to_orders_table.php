<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Cek kolom yang sudah ada
            $existingColumns = Schema::getColumnListing('orders');
            
            // Tambahkan kolom is_batch pertama
            if (!in_array('is_batch', $existingColumns)) {
                $table->boolean('is_batch')->default(false)->after('mockup_image');
            }
            
            // Tambahkan additional_fees dan related columns
            if (!in_array('additional_fees', $existingColumns)) {
                $table->json('additional_fees')->nullable()->after('material_needed');
            }
            
            if (!in_array('additional_fees_items', $existingColumns)) {
                $table->json('additional_fees_items')->nullable()->after('additional_fees');
            }
            
            if (!in_array('additional_fees_total', $existingColumns)) {
                $table->decimal('additional_fees_total', 10, 2)->default(0)->after('additional_fees_items');
            }
        });
    }

    public function down(): void
    {
        // Optional: untuk rollback
    }
};
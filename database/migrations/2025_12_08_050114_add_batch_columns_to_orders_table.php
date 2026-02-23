<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $existingColumns = Schema::getColumnListing('orders');
            
            // Pastikan is_batch sudah ada
            if (!in_array('is_batch', $existingColumns)) {
                $table->boolean('is_batch')->default(false)->after('mockup_image');
            }
            
            // Tambahkan kolom batch-related SETELAH is_batch
            if (!in_array('batch_items_data', $existingColumns)) {
                $table->json('batch_items_data')->nullable()->after('is_batch');
            }
            
            if (!in_array('batch_additional_fees_data', $existingColumns)) {
                $table->json('batch_additional_fees_data')->nullable()->after('batch_items_data');
            }
            
            if (!in_array('group_name', $existingColumns)) {
                $table->string('group_name')->nullable()->after('batch_additional_fees_data');
            }
        });
    }

    public function down(): void
    {
        // Optional: untuk rollback
    }
};
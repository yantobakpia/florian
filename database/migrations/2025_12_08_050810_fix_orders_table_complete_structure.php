<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $existingColumns = Schema::getColumnListing('orders');
            
            // ========== KOLOM UTAMA DENGAN DEFAULT VALUES ==========
            
            // 1. Kolom material_needed (wajib ada, default 0)
            if (!in_array('material_needed', $existingColumns)) {
                $table->decimal('material_needed', 10, 2)->default(0)->after('quantity');
            } else {
                $table->decimal('material_needed', 10, 2)->default(0)->change();
            }
            
            // 2. Kolom base_price (wajib ada, default 0)
            if (!in_array('base_price', $existingColumns)) {
                $table->decimal('base_price', 10, 2)->default(0)->after('material_needed');
            } else {
                $table->decimal('base_price', 10, 2)->default(0)->change();
            }
            
            // 3. Kolom size_surcharge
            if (!in_array('size_surcharge', $existingColumns)) {
                $table->decimal('size_surcharge', 10, 2)->default(0)->after('size');
            } else {
                $table->decimal('size_surcharge', 10, 2)->default(0)->change();
            }
            
            // 4. Kolom additional_fees (JSON)
            if (!in_array('additional_fees', $existingColumns)) {
                $table->json('additional_fees')->nullable()->after('material_needed');
            }
            
            // 5. Kolom additional_fees_items (JSON)
            if (!in_array('additional_fees_items', $existingColumns)) {
                $table->json('additional_fees_items')->nullable()->after('additional_fees');
            }
            
            // 6. Kolom additional_fees_total
            if (!in_array('additional_fees_total', $existingColumns)) {
                $table->decimal('additional_fees_total', 10, 2)->default(0)->after('additional_fees_items');
            } else {
                $table->decimal('additional_fees_total', 10, 2)->default(0)->change();
            }
            
            // 7. Kolom discount
            if (!in_array('discount', $existingColumns)) {
                $table->decimal('discount', 10, 2)->default(0)->after('additional_fees_total');
            } else {
                $table->decimal('discount', 10, 2)->default(0)->change();
            }
            
            // 8. Kolom total_price
            if (!in_array('total_price', $existingColumns)) {
                $table->decimal('total_price', 10, 2)->default(0)->after('discount');
            } else {
                $table->decimal('total_price', 10, 2)->default(0)->change();
            }
            
            // 9. Kolom dp_paid
            if (!in_array('dp_paid', $existingColumns)) {
                $table->decimal('dp_paid', 10, 2)->default(0)->after('total_price');
            } else {
                $table->decimal('dp_paid', 10, 2)->default(0)->change();
            }
            
            // 10. Kolom quantity (default 1)
            if (!in_array('quantity', $existingColumns)) {
                $table->integer('quantity')->default(1)->after('color');
            } else {
                $table->integer('quantity')->default(1)->change();
            }
            
            // ========== KOLOM LAINNYA DARI MODEL ==========
            
            // Kolom setelah clothing_type_id
            if (!in_array('custom_clothing_type', $existingColumns)) {
                $table->string('custom_clothing_type')->nullable()->after('clothing_type_id');
            }
            
            // Kolom setelah payment_status
            if (!in_array('payment_method', $existingColumns)) {
                $table->string('payment_method')->nullable()->after('payment_status');
            }
            
            // Kolom setelah order_status
            if (!in_array('priority', $existingColumns)) {
                $table->string('priority')->nullable()->after('order_status');
            }
            
            // Kolom tanggal
            if (!in_array('start_date', $existingColumns)) {
                $table->date('start_date')->nullable()->after('due_date');
            }
            
            if (!in_array('completion_date', $existingColumns)) {
                $table->date('completion_date')->nullable()->after('start_date');
            }
            
            // Kolom tailor_id
            if (!in_array('tailor_id', $existingColumns)) {
                $table->unsignedBigInteger('tailor_id')->nullable()->after('completion_date');
            }
            
            // Kolom notes
            $notesColumns = [
                'measurement_notes' => ['after' => 'tailor_id'],
                'production_notes' => ['after' => 'measurement_notes'],
                'customer_notes' => ['after' => 'production_notes'],
                'internal_notes' => ['after' => 'customer_notes'],
                'payment_notes' => ['after' => 'internal_notes'],
            ];
            
            foreach ($notesColumns as $columnName => $config) {
                if (!in_array($columnName, $existingColumns)) {
                    $table->text($columnName)->nullable()->after($config['after']);
                }
            }
            
            // Kolom images
            if (!in_array('reference_image', $existingColumns)) {
                $table->string('reference_image')->nullable()->after('payment_notes');
            }
            
            if (!in_array('mockup_image', $existingColumns)) {
                $table->string('mockup_image')->nullable()->after('reference_image');
            }
            
            // ========== KOLOM BATCH (TAMBAHKAN TERAKHIR) ==========
            
            // 11. Kolom is_batch (default false) - HARUS DITAMBAHKAN SEBELUM batch_items_data
            if (!in_array('is_batch', $existingColumns)) {
                $table->boolean('is_batch')->default(false)->after('mockup_image');
            } else {
                $table->boolean('is_batch')->default(false)->change();
            }
            
            // 12. Kolom batch_items_data (SETELAH is_batch)
            if (!in_array('batch_items_data', $existingColumns)) {
                $table->json('batch_items_data')->nullable()->after('is_batch');
            }
            
            // 13. Kolom batch_additional_fees_data
            if (!in_array('batch_additional_fees_data', $existingColumns)) {
                $table->json('batch_additional_fees_data')->nullable()->after('batch_items_data');
            }
            
            // 14. Kolom group_name
            if (!in_array('group_name', $existingColumns)) {
                $table->string('group_name')->nullable()->after('batch_additional_fees_data');
            }
        });
        
        // ========== UPDATE EXISTING NULL VALUES ==========
        $this->updateNullValues();
    }
    
    /**
     * Update existing records dengan NULL values
     */
    private function updateNullValues(): void
    {
        $columnsToUpdate = [
            'material_needed' => 0,
            'base_price' => 0,
            'size_surcharge' => 0,
            'additional_fees_total' => 0,
            'discount' => 0,
            'total_price' => 0,
            'dp_paid' => 0,
            'quantity' => 1,
            'is_batch' => false,
        ];
        
        foreach ($columnsToUpdate as $column => $defaultValue) {
            if (Schema::hasColumn('orders', $column)) {
                DB::table('orders')
                    ->whereNull($column)
                    ->update([$column => $defaultValue]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tidak melakukan apa-apa untuk safety
        // Jika perlu rollback, buat migration terpisah
    }
};
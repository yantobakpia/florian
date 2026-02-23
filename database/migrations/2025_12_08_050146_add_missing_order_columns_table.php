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
            
            // Daftar kolom yang perlu ditambahkan dalam urutan yang benar
            $columnsToAdd = [
                // Kolom setelah material_needed
                'additional_fees' => ['type' => 'json', 'after' => 'material_needed'],
                'additional_fees_items' => ['type' => 'json', 'after' => 'additional_fees'],
                'additional_fees_total' => ['type' => 'decimal', 'after' => 'additional_fees_items'],
                
                // Kolom setelah size
                'size_surcharge' => ['type' => 'decimal', 'after' => 'size'],
                
                // Kolom setelah clothing_type_id
                'custom_clothing_type' => ['type' => 'string', 'after' => 'clothing_type_id'],
                
                // Kolom setelah payment_status
                'payment_method' => ['type' => 'string', 'after' => 'payment_status'],
                
                // Kolom setelah order_status
                'priority' => ['type' => 'string', 'after' => 'order_status'],
                
                // Kolom tanggal
                'start_date' => ['type' => 'date', 'after' => 'due_date'],
                'completion_date' => ['type' => 'date', 'after' => 'start_date'],
                
                // Kolom tailor
                'tailor_id' => ['type' => 'foreignId', 'after' => 'completion_date'],
                
                // Kolom notes
                'measurement_notes' => ['type' => 'text', 'after' => 'tailor_id'],
                'production_notes' => ['type' => 'text', 'after' => 'measurement_notes'],
                'customer_notes' => ['type' => 'text', 'after' => 'production_notes'],
                'internal_notes' => ['type' => 'text', 'after' => 'customer_notes'],
                'payment_notes' => ['type' => 'text', 'after' => 'internal_notes'],
                
                // Kolom images
                'reference_image' => ['type' => 'string', 'after' => 'payment_notes'],
                'mockup_image' => ['type' => 'string', 'after' => 'reference_image'],
                
                // Kolom batch (ditambahkan terakhir setelah semua kolom di atas)
                'is_batch' => ['type' => 'boolean', 'after' => 'mockup_image'],
                'batch_items_data' => ['type' => 'json', 'after' => 'is_batch'],
                'batch_additional_fees_data' => ['type' => 'json', 'after' => 'batch_items_data'],
                'group_name' => ['type' => 'string', 'after' => 'batch_additional_fees_data'],
            ];
            
            foreach ($columnsToAdd as $columnName => $config) {
                if (!in_array($columnName, $existingColumns)) {
                    switch ($config['type']) {
                        case 'json':
                            $table->json($columnName)->nullable()->after($config['after']);
                            break;
                        case 'decimal':
                            $table->decimal($columnName, 10, 2)->default(0)->after($config['after']);
                            break;
                        case 'boolean':
                            $table->boolean($columnName)->default(false)->after($config['after']);
                            break;
                        case 'date':
                            $table->date($columnName)->nullable()->after($config['after']);
                            break;
                        case 'text':
                            $table->text($columnName)->nullable()->after($config['after']);
                            break;
                        case 'foreignId':
                            $table->foreignId($columnName)->nullable()->constrained('users')->after($config['after']);
                            break;
                        default:
                            $table->string($columnName)->nullable()->after($config['after']);
                            break;
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Biarkan kosong untuk safety
    }
};
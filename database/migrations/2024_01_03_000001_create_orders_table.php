<?php
// database/migrations/xxxx_xx_xx_xxxxxx_fix_orders_table_structure.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Hanya jalankan jika tabel orders belum memiliki semua kolom yang diperlukan
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $this->createOrdersTable($table);
            });
        } else {
            // Jika tabel sudah ada, tambahkan kolom yang hilang
            Schema::table('orders', function (Blueprint $table) {
                // Tambahkan kolom clothing_type_id jika belum ada
                if (!Schema::hasColumn('orders', 'clothing_type_id')) {
                    $table->foreignId('clothing_type_id')->nullable()->constrained('clothing_types')->nullOnDelete();
                }
                
                // Tambahkan kolom custom_clothing_type jika belum ada
                if (!Schema::hasColumn('orders', 'custom_clothing_type')) {
                    $table->string('custom_clothing_type')->nullable();
                }
                
                // Hapus kolom material_id hanya jika ada
                if (Schema::hasColumn('orders', 'material_id')) {
                    // Cek apakah foreign key ada sebelum menghapusnya
                    $foreignKeys = $this->getForeignKeys('orders', 'material_id');
                    if (count($foreignKeys) > 0) {
                        $table->dropForeign(['material_id']);
                    }
                    $table->dropColumn('material_id');
                }
                
                // Tambahkan kolom lainnya yang diperlukan
                $this->addMissingColumns($table);
            });
        }
    }

    public function down(): void
    {
        // Tidak perlu down migration untuk fix
    }

    private function createOrdersTable(Blueprint $table): void
    {
        $table->id();
        
        // Basic information
        $table->string('order_number')->unique();
        $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
        
        // Product information
        $table->foreignId('clothing_type_id')->nullable()->constrained('clothing_types')->nullOnDelete();
        $table->string('custom_clothing_type')->nullable();
        $table->string('size');
        $table->decimal('size_surcharge', 10, 2)->default(0);
        $table->string('color')->nullable();
        $table->integer('quantity')->default(1);
        $table->decimal('material_needed', 8, 2)->default(2.0);
        
        // Pricing
        $table->decimal('base_price', 12, 2);
        $table->decimal('additional_charges', 12, 2)->default(0);
        $table->decimal('discount', 12, 2)->default(0);
        $table->decimal('total_price', 12, 2);
        $table->decimal('dp_paid', 12, 2)->default(0);
        
        // Payment
        $table->enum('payment_status', ['unpaid', 'dp', 'paid'])->default('unpaid');
        $table->string('payment_method')->nullable();
        
        // Order status
        $table->enum('order_status', [
            'pending',
            'measurement',
            'cutting',
            'sewing',
            'finishing',
            'ready',
            'completed',
            'cancelled'
        ])->default('pending');
        
        $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
        
        // Dates
        $table->date('order_date');
        $table->date('due_date');
        $table->date('start_date')->nullable();
        $table->date('completion_date')->nullable();
        
        // Production
        $table->foreignId('tailor_id')->nullable()->constrained('users')->nullOnDelete();
        
        // Notes
        $table->text('measurement_notes')->nullable();
        $table->text('production_notes')->nullable();
        $table->text('customer_notes')->nullable();
        $table->text('internal_notes')->nullable();
        
        // Media
        $table->string('reference_image')->nullable();
        
        // Timestamps
        $table->timestamps();
        $table->softDeletes();
        
        // Indexes
        $table->index('order_number');
        $table->index('order_status');
        $table->index('payment_status');
        $table->index('due_date');
        $table->index(['order_status', 'due_date']);
        $table->index(['payment_status', 'order_status']);
    }

    private function addMissingColumns(Blueprint $table): void
    {
        $columns = [
            'size' => ['type' => 'string', 'nullable' => false, 'default' => 'M'],
            'size_surcharge' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0],
            'color' => ['type' => 'string', 'nullable' => true],
            'quantity' => ['type' => 'integer', 'default' => 1],
            'material_needed' => ['type' => 'decimal', 'precision' => 8, 'scale' => 2, 'default' => 2.0],
            'base_price' => ['type' => 'decimal', 'precision' => 12, 'scale' => 2],
            'additional_charges' => ['type' => 'decimal', 'precision' => 12, 'scale' => 2, 'default' => 0],
            'discount' => ['type' => 'decimal', 'precision' => 12, 'scale' => 2, 'default' => 0],
            'total_price' => ['type' => 'decimal', 'precision' => 12, 'scale' => 2],
            'dp_paid' => ['type' => 'decimal', 'precision' => 12, 'scale' => 2, 'default' => 0],
            'payment_status' => ['type' => 'string', 'default' => 'unpaid'],
            'payment_method' => ['type' => 'string', 'nullable' => true],
            'order_status' => ['type' => 'string', 'default' => 'pending'],
            'priority' => ['type' => 'string', 'default' => 'normal'],
            'order_date' => ['type' => 'date'],
            'due_date' => ['type' => 'date'],
            'start_date' => ['type' => 'date', 'nullable' => true],
            'completion_date' => ['type' => 'date', 'nullable' => true],
            'measurement_notes' => ['type' => 'text', 'nullable' => true],
            'production_notes' => ['type' => 'text', 'nullable' => true],
            'customer_notes' => ['type' => 'text', 'nullable' => true],
            'internal_notes' => ['type' => 'text', 'nullable' => true],
            'reference_image' => ['type' => 'string', 'nullable' => true],
        ];

        foreach ($columns as $column => $config) {
            if (!Schema::hasColumn('orders', $column)) {
                switch ($config['type']) {
                    case 'string':
                        if ($config['nullable'] ?? false) {
                            $table->string($column)->nullable();
                        } else {
                            $table->string($column)->default($config['default'] ?? null);
                        }
                        break;
                    case 'decimal':
                        if (isset($config['nullable']) && $config['nullable']) {
                            $table->decimal($column, $config['precision'], $config['scale'])->nullable();
                        } else {
                            $table->decimal($column, $config['precision'], $config['scale'])->default($config['default'] ?? 0);
                        }
                        break;
                    case 'integer':
                        $table->integer($column)->default($config['default'] ?? 0);
                        break;
                    case 'date':
                        if ($config['nullable'] ?? false) {
                            $table->date($column)->nullable();
                        } else {
                            $table->date($column);
                        }
                        break;
                    case 'text':
                        $table->text($column)->nullable();
                        break;
                }
            }
        }

        // Tambahkan tailor_id sebagai foreign key
        if (!Schema::hasColumn('orders', 'tailor_id')) {
            $table->foreignId('tailor_id')->nullable()->constrained('users')->nullOnDelete();
        }
    }

    private function getForeignKeys(string $table, string $column): array
    {
        try {
            $connection = DB::connection();
            $databaseName = $connection->getDatabaseName();
            
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$databaseName, $table, $column]);
            
            return $foreignKeys;
        } catch (\Exception $e) {
            return [];
        }
    }
};
<?php
// database/migrations/xxxx_xx_xx_add_financial_columns_to_orders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Tambahkan jika belum ada
            if (!Schema::hasColumn('orders', 'dp_paid')) {
                $table->decimal('dp_paid', 12, 2)->default(0)->after('total_price');
            }
            
            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->enum('payment_status', ['unpaid', 'dp', 'paid', 'partial', 'cancelled'])->default('unpaid')->after('dp_paid');
            }
            
            if (!Schema::hasColumn('orders', 'payment_method')) {
                $table->enum('payment_method', ['cash', 'transfer', 'qris', 'other'])->nullable()->after('payment_status');
            }
            
            if (!Schema::hasColumn('orders', 'material_needed')) {
                $table->decimal('material_needed', 8, 2)->default(2)->after('custom_clothing_type');
            }
            
            if (!Schema::hasColumn('orders', 'additional_charges')) {
                $table->decimal('additional_charges', 12, 2)->default(0)->after('base_price');
            }
            
            if (!Schema::hasColumn('orders', 'size_surcharge')) {
                $table->decimal('size_surcharge', 12, 2)->default(0)->after('additional_charges');
            }
            
            if (!Schema::hasColumn('orders', 'balance_updated')) {
                $table->boolean('balance_updated')->default(false)->after('payment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'dp_paid',
                'payment_status',
                'payment_method',
                'material_needed',
                'additional_charges',
                'size_surcharge',
                'balance_updated'
            ]);
        });
    }
};
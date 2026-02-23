<?php
// database/migrations/xxxx_xx_xx_create_material_purchases_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_id');
            $table->string('supplier');
            $table->string('invoice_number')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 15, 2);
            $table->enum('payment_method', ['cash', 'transfer', 'qris', 'credit'])->default('cash');
            $table->enum('status', ['pending', 'ordered', 'received', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->date('purchase_date');
            $table->date('received_date')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['purchase_date', 'material_id']);
            $table->index('supplier');
            $table->unique('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_purchases');
    }
};
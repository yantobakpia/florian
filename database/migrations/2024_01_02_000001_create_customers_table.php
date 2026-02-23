<?php
// database/migrations/2024_01_02_000001_create_customers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('measurement_notes')->nullable(); // catatan ukuran badan
            $table->text('preferences')->nullable(); // preferensi bahan/style
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->date('first_order_date')->nullable();
            $table->date('last_order_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('name');
            $table->index('phone');
            $table->index('email');
            $table->index('total_orders');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
<?php
// database/migrations/xxxx_xx_xx_create_batch_clothing_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_clothing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('clothing_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('custom_name')->nullable()->comment('Nama custom jika tidak ada clothing_type');
            $table->decimal('base_price', 12, 2)->default(0);
            $table->string('color')->nullable();
            $table->json('size_distribution')->nullable()->comment('JSON: {"XS": 0, "S": 0, ...}');
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index(['order_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_clothing_items');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_calculations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            
            // BAHAN (KELUAR)
            $table->decimal('fabric_cost', 15, 2)->default(0);
            $table->decimal('thread_cost', 15, 2)->default(0);
            $table->decimal('button_cost', 15, 2)->default(0);
            $table->decimal('zipper_cost', 15, 2)->default(0);
            $table->decimal('lining_cost', 15, 2)->default(0);
            $table->decimal('other_material_cost', 15, 2)->default(0);
            
            // JASA (KELUAR)
            $table->decimal('sewing_cost', 15, 2)->default(0);
            $table->decimal('embroidery_cost', 15, 2)->default(0);
            $table->decimal('printing_cost', 15, 2)->default(0);
            $table->decimal('ironing_cost', 15, 2)->default(0);
            $table->decimal('other_service_cost', 15, 2)->default(0);
            
            // TOTAL & PROFIT
            $table->decimal('total_material_cost', 15, 2)->default(0);
            $table->decimal('total_service_cost', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('order_price', 15, 2)->default(0);
            $table->decimal('profit', 15, 2)->default(0);
            $table->decimal('profit_percentage', 8, 2)->default(0);
            
            // DETAIL BAHAN
            $table->json('fabric_details')->nullable();
            $table->json('material_details')->nullable();
            $table->json('service_details')->nullable();
            
            // TAMBAHAN
            $table->decimal('fabric_length', 8, 2)->default(0);
            $table->decimal('fabric_price_per_meter', 15, 2)->default(0);
            $table->text('sewing_notes')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_calculations');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'is_batch_multi_item')) {
                $table->boolean('is_batch_multi_item')->default(false)->after('is_batch');
            }
            
            if (!Schema::hasColumn('orders', 'batch_color')) {
                $table->string('batch_color')->nullable()->after('group_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'is_batch_multi_item')) {
                $table->dropColumn('is_batch_multi_item');
            }
            
            if (Schema::hasColumn('orders', 'batch_color')) {
                $table->dropColumn('batch_color');
            }
        });
    }
};
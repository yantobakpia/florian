<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('notes');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->timestamp('current_login_at')->nullable()->after('last_login_ip');
            $table->string('current_login_ip')->nullable()->after('current_login_at');
            $table->integer('login_count')->default(0)->after('current_login_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_login_at',
                'last_login_ip',
                'current_login_at',
                'current_login_ip',
                'login_count'
            ]);
        });
    }
};
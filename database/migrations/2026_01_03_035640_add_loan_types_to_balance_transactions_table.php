<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_loan_types_to_balance_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Untuk MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE balance_transactions MODIFY COLUMN type ENUM('in', 'out', 'loan_borrow', 'loan_lend') NOT NULL");
        }
        
        // Untuk PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE balance_transactions DROP CONSTRAINT IF EXISTS balance_transactions_type_check");
            DB::statement("ALTER TABLE balance_transactions ADD CONSTRAINT balance_transactions_type_check CHECK (type IN ('in', 'out', 'loan_borrow', 'loan_lend'))");
        }
        
        // Untuk SQLite (tidak support ALTER untuk enum, perlu recreate)
        if (DB::getDriverName() === 'sqlite') {
            // Back up data
            $rows = DB::table('balance_transactions')->get();
            
            // Drop table
            Schema::dropIfExists('balance_transactions');
            
            // Recreate table with new enum
            Schema::create('balance_transactions', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['in', 'out', 'loan_borrow', 'loan_lend']);
                $table->integer('amount');
                $table->integer('balance_before')->default(0);
                $table->integer('balance_after')->default(0);
                $table->string('description');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('payment_method')->default('cash');
                $table->text('notes')->nullable();
                $table->dateTime('transaction_date');
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->softDeletes();
                $table->timestamps();
            });
            
            // Restore data
            foreach ($rows as $row) {
                DB::table('balance_transactions')->insert((array) $row);
            }
        }
    }

    public function down(): void
    {
        // Kembalikan ke enum lama
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE balance_transactions MODIFY COLUMN type ENUM('in', 'out') NOT NULL");
        }
        
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE balance_transactions DROP CONSTRAINT IF EXISTS balance_transactions_type_check");
            DB::statement("ALTER TABLE balance_transactions ADD CONSTRAINT balance_transactions_type_check CHECK (type IN ('in', 'out'))");
        }
        
        if (DB::getDriverName() === 'sqlite') {
            $rows = DB::table('balance_transactions')->get();
            
            Schema::dropIfExists('balance_transactions');
            
            Schema::create('balance_transactions', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['in', 'out']);
                $table->integer('amount');
                $table->integer('balance_before')->default(0);
                $table->integer('balance_after')->default(0);
                $table->string('description');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('payment_method')->default('cash');
                $table->text('notes')->nullable();
                $table->dateTime('transaction_date');
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->softDeletes();
                $table->timestamps();
            });
            
            // Hanya insert data dengan type 'in' atau 'out'
            foreach ($rows as $row) {
                if (in_array($row->type, ['in', 'out'])) {
                    DB::table('balance_transactions')->insert((array) $row);
                }
            }
        }
    }
};
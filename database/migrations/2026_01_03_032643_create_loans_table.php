<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number')->unique();
            $table->enum('loan_type', ['borrow', 'lend']);
            $table->string('borrower_type');
            $table->unsignedBigInteger('borrower_id');
            $table->integer('amount');
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->integer('installment_count')->default(1);
            $table->integer('installment_amount')->default(0);
            $table->integer('total_amount')->default(0);
            $table->integer('remaining_amount')->default(0);
            $table->enum('status', ['pending', 'active', 'paid', 'overdue', 'partial', 'defaulted'])->default('pending');
            $table->dateTime('loan_date');
            $table->date('due_date');
            $table->dateTime('paid_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['borrower_type', 'borrower_id']);
            $table->index('status');
            $table->index('loan_date');
        });
        
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->integer('installment_number');
            $table->integer('amount');
            $table->string('payment_method')->default('cash');
            $table->dateTime('payment_date');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('balance_transaction_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['loan_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('loans');
    }
};
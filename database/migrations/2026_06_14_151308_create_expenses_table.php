<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('paid_by')->constrained('users')->onDelete('cascade');
            $table->string('description');
            $table->decimal('amount', 15, 4);
            $table->string('currency', 3)->default('INR');
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);
            $table->date('expense_date');
            $table->string('split_type')->default('equal'); // equal, exact, percentage
            $table->boolean('is_settlement')->default(false);
            $table->string('status')->default('active'); // active, pending_approval, deleted
            $table->timestamps();

            $table->index(['group_id', 'expense_date']);
            $table->index('paid_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

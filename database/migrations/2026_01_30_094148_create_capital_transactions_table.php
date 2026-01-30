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
        Schema::create('capital_transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->foreignId('loan_id')->nullable()->constrained('loans')->onDelete('set null');
            $table->enum('type', ['deduction', 'addition', 'manual'])->default('deduction');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capital_transactions');
    }
};

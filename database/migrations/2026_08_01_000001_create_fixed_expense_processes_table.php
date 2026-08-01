<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_expense_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_expense_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('expense_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->date('target_month');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->string('memo');
            $table->timestamps();

            $table->unique(['fixed_expense_id', 'target_month']);
            $table->index(['user_id', 'target_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_expense_processes');
    }
};

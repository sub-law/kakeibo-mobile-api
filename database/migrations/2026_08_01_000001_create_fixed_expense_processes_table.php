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
            $table->foreignId('fixed_expense_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('expense_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->date('target_month');
            $table->timestamps();

            $table->unique(
                ['fixed_expense_id', 'target_month'],
                'fixed_expense_processes_expense_month_unique'
            );
            $table->unique('expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_expense_processes');
    }
};

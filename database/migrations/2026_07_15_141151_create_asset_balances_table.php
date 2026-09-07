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
        Schema::create('asset_balances', function (Blueprint $table) {
            $table->id();

            // ユーザー（users）とのリレーション
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // 口座（accounts）とのリレーション
            $table->foreignId('account_id')
                ->constrained('accounts')
                ->onDelete('cascade');

            // 月次残高（未入力は null → フロント側で 0 扱い）
            $table->integer('amount')->nullable();

            // 月初固定値で登録する日付（YYYY-MM-01）
            $table->date('date');

            $table->timestamps();

            // 同じユーザー・同じ口座・同じ月の重複を禁止
            $table->unique(['user_id', 'account_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_balances');
    }
};

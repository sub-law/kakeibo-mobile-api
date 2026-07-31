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
        Schema::create('budget_alert_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_alert_setting_id')
                ->constrained()
                ->onDelete('cascade');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('level', 20);
            $table->timestamp('read_at');

            $table->unique(
                ['budget_alert_setting_id', 'year', 'month', 'level'],
                'budget_alert_reads_setting_period_level_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_alert_reads');
    }
};

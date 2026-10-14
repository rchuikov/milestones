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
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->boolean('show_round_days')->default(true);
            $table->boolean('show_half_decade_days')->default(true);
            $table->boolean('show_round_weeks')->default(true);
            $table->boolean('show_round_months')->default(true);
            $table->boolean('show_years')->default(true);

            $table->timestamps();
        });

        Schema::create('milestone_periods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('milestone_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();

            $table->index(['milestone_id', 'finished_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestone_periods');
        Schema::dropIfExists('milestones');
    }
};

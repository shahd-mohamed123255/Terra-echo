<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crops', function (Blueprint $table) {
            $table->string('category')->nullable();
            $table->unsignedSmallInteger('water_mm')->nullable();
            $table->unsignedInteger('water_m3_per_ha')->nullable();
            $table->string('season')->nullable();
            $table->string('season_months')->nullable();
            $table->unsignedSmallInteger('duration_min_days')->nullable();
            $table->unsignedSmallInteger('duration_max_days')->nullable();
            $table->string('preferred_soil')->nullable();
            $table->string('image')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crops', function (Blueprint $table) {
            $table->dropColumn([
                'category', 'water_mm', 'water_m3_per_ha', 'season', 'season_months',
                'duration_min_days', 'duration_max_days', 'preferred_soil', 'image',
            ]);
        });
    }
};
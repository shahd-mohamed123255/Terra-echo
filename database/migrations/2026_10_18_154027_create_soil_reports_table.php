<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soil_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();

            $table->decimal('salinity', 8, 2)->nullable();
            $table->decimal('ph', 4, 2)->nullable();
            $table->decimal('nitrogen', 8, 2)->nullable();
            $table->decimal('phosphorus', 8, 2)->nullable();
            $table->decimal('potassium', 8, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soil_reports');
    }
};   
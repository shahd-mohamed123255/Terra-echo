<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_crop', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crop_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // previous | considering
            $table->timestamps();

            $table->unique(['farm_id', 'crop_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_crop');
    }
};
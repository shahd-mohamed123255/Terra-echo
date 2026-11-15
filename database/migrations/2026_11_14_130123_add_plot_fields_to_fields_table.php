<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                       // P1
            $table->decimal('area', 8, 2);                // 6.00
            $table->string('status')->default('fallow');  // planted / fallow
            $table->unique(['farm_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropUnique(['farm_id', 'name']);
            $table->dropConstrainedForeignId('crop_id');
            $table->dropConstrainedForeignId('farm_id');
            $table->dropColumn(['name', 'area', 'status']);
        });
    }
};
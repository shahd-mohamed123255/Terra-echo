<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('user_id');

            // Step 2
            $table->decimal('area', 10, 2)->nullable();
            $table->string('area_unit')->default('feddan');
            $table->foreignId('soil_type_id')->nullable()->constrained()->nullOnDelete();

            // Step 3
            $table->string('irrigation_source')->nullable();
            $table->string('priority')->nullable();
            $table->string('target_season')->nullable();
            $table->unsignedTinyInteger('planning_horizon')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('soil_type_id');
            $table->dropColumn([
                'status', 'area', 'area_unit', 'irrigation_source',
                'priority', 'target_season', 'planning_horizon',
            ]);
        });
    }
};
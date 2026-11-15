<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crop_plans', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->json('settings');
            $table->json('plots');
            $table->string('status')->default('draft');
            $table->index(['user_id', 'farm_id', 'status']);
        });

        Schema::table('simulations', function (Blueprint $table) {
            $table->foreignId('selected_plan_id')->nullable()->constrained('crop_plans')->nullOnDelete();
            $table->foreignId('chat_conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('simulations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_plan_id');
            $table->dropConstrainedForeignId('chat_conversation_id');
        });

        Schema::table('crop_plans', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'farm_id', 'status']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('farm_id');
            $table->dropColumn(['name', 'settings', 'plots', 'status']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('plan', 'starter')->update(['plan' => 'water-saving']);
        DB::table('users')->where('plan', 'professional')->update(['plan' => 'balanced']);
        DB::table('users')->where('plan', 'enterprise')->update(['plan' => 'high-yield']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('plan')->default('water-saving')->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('plan', 'water-saving')->update(['plan' => 'starter']);
        DB::table('users')->where('plan', 'balanced')->update(['plan' => 'professional']);
        DB::table('users')->where('plan', 'high-yield')->update(['plan' => 'enterprise']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('plan')->default('starter')->change();
        });
    }
};

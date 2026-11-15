<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TRACKING_TABLE = 'simulation_stage_b_added_columns';

    public function up(): void
    {
        if (! Schema::hasTable(self::TRACKING_TABLE)) {
            Schema::create(self::TRACKING_TABLE, function (Blueprint $table) {
                $table->string('column_name')->primary();
            });
        }

        $this->addIfMissing('stage', fn (Blueprint $table) => $table->string('stage')->nullable());
        $this->addIfMissing('error', fn (Blueprint $table) => $table->text('error')->nullable());
        $this->addIfMissing('started_at', fn (Blueprint $table) => $table->timestamp('started_at')->nullable());
        $this->addIfMissing('finished_at', fn (Blueprint $table) => $table->timestamp('finished_at')->nullable());
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TRACKING_TABLE)) {
            return;
        }

        foreach (Schema::getConnection()->table(self::TRACKING_TABLE)->pluck('column_name') as $column) {
            if (Schema::hasColumn('simulations', $column)) {
                Schema::table('simulations', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        Schema::drop(self::TRACKING_TABLE);
    }

    private function addIfMissing(string $column, Closure $definition): void
    {
        if (Schema::hasColumn('simulations', $column)) {
            return;
        }

        Schema::getConnection()->table(self::TRACKING_TABLE)->insert(['column_name' => $column]);
        Schema::table('simulations', $definition);
    }
};

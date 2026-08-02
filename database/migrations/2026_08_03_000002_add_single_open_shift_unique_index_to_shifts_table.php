<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Partial unique index: at most ONE shift may be open (closed_at IS
     * NULL) at a time. Enforced at the DB level so two concurrent "open
     * shift" requests cannot create a second open shift.
     */
    public function up(): void
    {
        // Index the BOOLEAN expression (closed_at IS NULL), not the column:
        // plain columns treat every NULL as distinct, so a unique index on
        // (closed_at) would still allow any number of open shifts. All open
        // rows evaluate to true and are the only ones indexed, so exactly
        // one open shift is allowed.
        DB::statement('CREATE UNIQUE INDEX shifts_single_open_unique ON shifts ((closed_at IS NULL)) WHERE closed_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function ($table) {
            $table->dropIndex('shifts_single_open_unique');
        });
    }
};

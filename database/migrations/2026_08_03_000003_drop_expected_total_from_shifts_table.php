<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * shifts.expected_total is dead: closing cash expectations come from
     * Shift::expectedCash() (opening + cash paid − refunds + deposits −
     * petty out), which is authoritative and consistent with the Z-report.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('expected_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->unsignedInteger('expected_total')->nullable()->after('closing_cash');
        });
    }
};

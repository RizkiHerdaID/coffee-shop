<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('loyalty_credited_at')->nullable()->after('notes');
        });

        // Orders that are already paid have earned their stamp under the
        // previous observer behavior; backfill the flag so legacy orders
        // never get credited a second time on a later edit.
        DB::table('orders')
            ->where('status', 'paid')
            ->whereNotNull('customer_phone')
            ->update(['loyalty_credited_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('loyalty_credited_at');
        });
    }
};

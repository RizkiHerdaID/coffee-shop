<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->unique('name');
        });

        Schema::table('stock_items', function (Blueprint $table): void {
            $table->unique('name');
        });

        Schema::table('promos', function (Blueprint $table): void {
            $table->unique('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropUnique(['name']);
        });

        Schema::table('stock_items', function (Blueprint $table): void {
            $table->dropUnique(['name']);
        });

        Schema::table('promos', function (Blueprint $table): void {
            $table->dropUnique(['title']);
        });
    }
};

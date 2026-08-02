<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('cost')->nullable()->after('min_threshold');
        });

        Schema::create('menu_item_stock_item', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['menu_item_id', 'stock_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_stock_item');
        Schema::table('stock_items', function (Blueprint $table): void {
            $table->dropColumn('cost');
        });
    }
};

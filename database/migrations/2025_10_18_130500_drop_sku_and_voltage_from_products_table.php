<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop unique index on sku if it exists (conventional name)
            try {
                $table->dropUnique('products_sku_unique');
            } catch (\Throwable $e) {
                // ignore if index doesn't exist or driver auto-drops with column
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'voltage')) {
                $table->dropColumn('voltage');
            }
            if (Schema::hasColumn('products', 'sku')) {
                $table->dropColumn('sku');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'sku')) {
                $table->string('sku')->nullable()->unique()->after('price');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'voltage')) {
                // place after sku if present, otherwise after price
                if (Schema::hasColumn('products', 'sku')) {
                    $table->string('voltage')->nullable()->after('sku');
                } else {
                    $table->string('voltage')->nullable()->after('price');
                }
            }
        });
    }
};

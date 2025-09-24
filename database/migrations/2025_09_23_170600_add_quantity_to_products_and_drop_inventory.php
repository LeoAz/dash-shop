<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add quantity to products
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'quantity')) {
                $table->unsignedInteger('quantity')->default(0)->after('price');
            }
        });

        // Remove inventory_id from product_sales if exists
        if (Schema::hasColumn('product_sales', 'inventory_id')) {
            Schema::table('product_sales', function (Blueprint $table) {
                $table->dropConstrainedForeignId('inventory_id');
            });
        }

        // Drop inventories table if exists
        if (Schema::hasTable('inventories')) {
            Schema::drop('inventories');
        }
    }

    public function down(): void
    {
        // Recreate inventories table (basic structure) for rollback safety
        if (!Schema::hasTable('inventories')) {
            Schema::create('inventories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->integer('quantity')->default(0);
                $table->integer('minimum_stock')->default(0);
                $table->timestamps();
            });
        }

        // Restore column on product_sales
        Schema::table('product_sales', function (Blueprint $table) {
            if (!Schema::hasColumn('product_sales', 'inventory_id')) {
                $table->foreignId('inventory_id')->nullable()->constrained()->nullOnDelete();
            }
        });

        // Remove quantity from products on rollback
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'quantity')) {
                $table->dropColumn('quantity');
            }
        });
    }
};

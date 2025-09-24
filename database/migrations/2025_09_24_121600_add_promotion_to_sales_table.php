<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('status')->constrained('promotions')->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->nullable()->after('promotion_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
            $table->dropConstrainedForeignId('promotion_id');
        });
    }
};

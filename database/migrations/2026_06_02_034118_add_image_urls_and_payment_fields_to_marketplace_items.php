<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_items', function (Blueprint $table) {
            // Only add columns that don't exist yet
            if (!Schema::hasColumn('marketplace_items', 'image_urls')) {
                $table->json('image_urls')->nullable()->after('image');
            }

            if (!Schema::hasColumn('marketplace_items', 'stock')) {
                $table->integer('stock')->default(1)->after('image_urls');
            }

            if (!Schema::hasColumn('marketplace_items', 'accepts_cash')) {
                $table->boolean('accepts_cash')->default(true)->after('stock');
            }

            if (!Schema::hasColumn('marketplace_items', 'accepts_gcash')) {
                $table->boolean('accepts_gcash')->default(false)->after('accepts_cash');
            }

            if (!Schema::hasColumn('marketplace_items', 'accepts_qrph')) {
                $table->boolean('accepts_qrph')->default(false)->after('accepts_gcash');
            }

            if (!Schema::hasColumn('marketplace_items', 'gcash_name')) {
                $table->string('gcash_name')->nullable()->after('accepts_qrph');
            }

            if (!Schema::hasColumn('marketplace_items', 'gcash_number')) {
                $table->string('gcash_number')->nullable()->after('gcash_name');
            }

            if (!Schema::hasColumn('marketplace_items', 'qrph_image_url')) {
                $table->string('qrph_image_url')->nullable()->after('gcash_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_items', function (Blueprint $table) {
            $table->dropColumn([
                'image_urls', 'stock', 'accepts_cash', 'accepts_gcash',
                'accepts_qrph', 'gcash_name', 'gcash_number', 'qrph_image_url'
            ]);
        });
    }
};
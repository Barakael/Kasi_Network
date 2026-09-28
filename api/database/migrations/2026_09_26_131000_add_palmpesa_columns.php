<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->text('palmpesa_api_token')->nullable()->after('snippe_webhook_secret');
            $table->string('palmpesa_user_id', 32)->nullable()->after('palmpesa_api_token');
            $table->string('palmpesa_vendor', 32)->nullable()->after('palmpesa_user_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('palmpesa_order_id')->nullable()->unique()->after('snippe_external_reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['palmpesa_order_id']);
            $table->dropColumn('palmpesa_order_id');
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['palmpesa_api_token', 'palmpesa_user_id', 'palmpesa_vendor']);
        });
    }
};

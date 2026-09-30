<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->string('abbreviation', 16)->nullable()->after('name');
            $table->unique(['tenant_id', 'abbreviation']);
        });

        Schema::table('voucher_batches', function (Blueprint $table): void {
            $table->timestamp('shelf_expires_at')->nullable()->after('notes');
        });

        Schema::create('platform_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('payee_name')->nullable();
            $table->string('account_number')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_profiles');

        Schema::table('voucher_batches', function (Blueprint $table): void {
            $table->dropColumn('shelf_expires_at');
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'abbreviation']);
            $table->dropColumn('abbreviation');
        });
    }
};
